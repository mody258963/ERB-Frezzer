<?php

namespace App\Services;

use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\ProductReturn;
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
                if ($return->return_type === ReturnType::CustomerReturn && $res === ReturnResolution::Restock) {
                    $stock = $this->stock->firstOrCreate($item->part_id, $return->branch_id);
                    $this->stock->adjustQuantity($stock, $item->quantity);
                    $this->movements->create([
                        'part_id' => $item->part_id,
                        'branch_id' => $return->branch_id,
                        'movement_type' => StockMovementType::ReturnIn,
                        'quantity' => $item->quantity,
                        'reference_id' => $return->id,
                        'reference_type' => 'return',
                        'notes' => 'Customer return restock',
                        'created_by' => $user->id,
                        'created_at' => now(),
                    ]);
                }

                if ($return->return_type === ReturnType::CustomerReturn && $res === ReturnResolution::CreditNote) {
                    $customer = Customer::query()->lockForUpdate()->findOrFail($return->customer_id);
                    $customer->outstanding_balance = bcsub((string) $customer->outstanding_balance, (string) $item->total, 2);
                    $customer->save();
                }

                if ($return->return_type === ReturnType::SupplierReturn && $res === ReturnResolution::SupplierCredit) {
                    $supplier = Supplier::query()->lockForUpdate()->findOrFail($return->supplier_id);
                    $supplier->total_debt = bcsub((string) $supplier->total_debt, (string) $item->total, 2);
                    $supplier->save();
                }

                if ($res === ReturnResolution::Writeoff) {
                    // Remove from stock permanently — deduct if restocked earlier; for writeoff from customer return we deduct branch stock
                    $stock = $this->stock->lockForPartAndBranch($item->part_id, $return->branch_id);
                    if ($stock && $stock->quantity >= $item->quantity) {
                        $this->stock->adjustQuantity($stock, -1 * $item->quantity);
                        $this->movements->create([
                            'part_id' => $item->part_id,
                            'branch_id' => $return->branch_id,
                            'movement_type' => StockMovementType::Adjustment,
                            'quantity' => -1 * $item->quantity,
                            'reference_id' => $return->id,
                            'reference_type' => 'return',
                            'notes' => 'Write-off defective return',
                            'created_by' => $user->id,
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            $return->status = ReturnStatus::Completed;
            $return->save();
        });

        $this->audit->record($user, 'return.approve', 'return', $return->id, null, $return->fresh()?->toArray());
        $this->dashboardCache->forgetSummary();

        return $return->fresh(['items']);
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

        return $return;
    }
}
