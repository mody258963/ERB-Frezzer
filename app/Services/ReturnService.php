<?php

namespace App\Services;

use App\Enums\ReturnItemCondition;
use App\Enums\ReturnReferenceType;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        private ProductReturnRepositoryInterface $returns,
        private StockRepositoryInterface $stock,
        private StockMovementRepositoryInterface $movements,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
        private ReturnQuantityValidator $returnQuantities,
    ) {}

    public function approve(User $user, ProductReturn $return, string $resolution): ProductReturn
    {
        if ($return->status !== ReturnStatus::Pending) {
            throw new \InvalidArgumentException('Return is not pending.');
        }

        $res = ReturnResolution::from($resolution);

        DB::transaction(function () use ($user, $return, $res) {
            $return->resolution = $res;
            $return->approved_by = $user->id;
            $return->load('items');

            foreach ($return->items as $item) {
                if ($return->return_type === ReturnType::CustomerReturn) {
                    $this->approveCustomerReturnItem($user, $return, $item, $res);
                }

                if ($return->return_type === ReturnType::SupplierReturn) {
                    $this->approveSupplierReturnItem($user, $return, $item, $res);
                }
            }

            $return->status = ReturnStatus::Completed;
            $return->completed_at = now();
            $return->save();
        });

        $this->audit->record($user, 'return.approve', 'return', $return->id, null, $return->fresh()?->toArray());
        $this->dashboardCache->forgetAllSummaries();
        $this->syncReferenceReturnStatus($return->fresh());

        return $return->fresh(['items']);
    }

    private function approveCustomerReturnItem(
        User $user,
        ProductReturn $return,
        ReturnItem $item,
        ReturnResolution $res,
    ): void {
        if ($this->shouldRestockCustomerItem($item, $res)) {
            $this->addStock(
                $user,
                $return,
                $item,
                StockMovementType::ReturnIn,
                $item->condition === ReturnItemCondition::Sellable
                    ? 'Customer return — restock'
                    : 'Customer return restock',
            );
        }

        if ($res === ReturnResolution::CreditNote) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($return->customer_id);
            $customer->outstanding_balance = bcsub((string) $customer->outstanding_balance, (string) $item->total, 2);
            $customer->save();
        }
    }

    /**
     * Sellable goods go back to inventory. Defective goods do not (scrap), but customer still gets money on refund/writeoff.
     */
    private function shouldRestockCustomerItem(ReturnItem $item, ReturnResolution $res): bool
    {
        if ($item->condition === ReturnItemCondition::Defective) {
            return false;
        }

        return in_array($res, [
            ReturnResolution::Restock,
            ReturnResolution::RefundCash,
            ReturnResolution::CreditNote,
            ReturnResolution::Replace,
        ], true);
    }

    private function approveSupplierReturnItem(
        User $user,
        ProductReturn $return,
        ReturnItem $item,
        ReturnResolution $res,
    ): void {
        if (in_array($res, [
            ReturnResolution::SupplierCredit,
            ReturnResolution::Writeoff,
        ], true)) {
            $this->deductStock(
                $user,
                $return,
                $item,
                StockMovementType::ReturnOut,
                'Supplier return — stock out',
            );
        }

        if ($res === ReturnResolution::SupplierCredit) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($return->supplier_id);
            $supplier->total_debt = bcsub((string) $supplier->total_debt, (string) $item->total, 2);
            $supplier->save();
        }
    }

    private function addStock(
        User $user,
        ProductReturn $return,
        ReturnItem $item,
        StockMovementType $type,
        string $notes,
    ): void {
        $stock = $this->stock->firstOrCreate($item->part_id, $return->branch_id);
        $this->stock->adjustQuantity($stock, $item->quantity);
        $this->movements->create([
            'part_id' => $item->part_id,
            'branch_id' => $return->branch_id,
            'movement_type' => $type,
            'quantity' => $item->quantity,
            'reference_id' => $return->id,
            'reference_type' => 'return',
            'notes' => $notes,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    private function deductStock(
        User $user,
        ProductReturn $return,
        ReturnItem $item,
        StockMovementType $type,
        string $notes,
    ): void {
        $stock = $this->stock->lockForPartAndBranch($item->part_id, $return->branch_id);
        if (! $stock || $stock->quantity < $item->quantity) {
            throw new \InvalidArgumentException(
                "Insufficient stock to complete supplier return for part {$item->part_id}."
            );
        }

        $this->stock->adjustQuantity($stock, -1 * $item->quantity);
        $this->movements->create([
            'part_id' => $item->part_id,
            'branch_id' => $return->branch_id,
            'movement_type' => $type,
            'quantity' => -1 * $item->quantity,
            'reference_id' => $return->id,
            'reference_type' => 'return',
            'notes' => $notes,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    public function reject(User $user, ProductReturn $return, string $reason): ProductReturn
    {
        if ($return->status !== ReturnStatus::Pending) {
            throw new \InvalidArgumentException('Return is not pending.');
        }

        $before = $return->toArray();
        $return->status = ReturnStatus::Rejected;
        $return->notes = trim((string) $return->notes."\nRejected: ".$reason);
        $return->save();

        $this->audit->record($user, 'return.reject', 'return', $return->id, $before, $return->toArray());
        $this->dashboardCache->forgetAllSummaries();
        $this->syncReferenceReturnStatus($return->fresh());

        return $return;
    }

    private function syncReferenceReturnStatus(ProductReturn $return): void
    {
        if ($return->return_type === ReturnType::CustomerReturn
            && $return->reference_type === ReturnReferenceType::Invoice) {
            $this->returnQuantities->syncInvoiceReturnStatus($return->reference_id);
        }
    }
}
