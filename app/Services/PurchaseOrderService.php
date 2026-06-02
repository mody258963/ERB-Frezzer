<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchasePaymentType;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrders,
        private StockRepositoryInterface $stock,
        private StockMovementRepositoryInterface $movements,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @param  array{supplier_id: string, branch_id: string, description?: ?string, total_amount?: string|float, payment_type: string, installment_count?: int, installment_start_date?: string, items: list<array{part_id: string, quantity: int, unit_cost: string|float}>}  $data
     */
    public function create(User $user, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($user, $data) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);

            $items = [];
            $calculated = '0';
            foreach ($data['items'] as $row) {
                $line = bcmul((string) $row['unit_cost'], (string) $row['quantity'], 2);
                $calculated = bcadd($calculated, $line, 2);
                $items[] = [
                    'part_id' => $row['part_id'],
                    'quantity' => $row['quantity'],
                    'unit_cost' => (string) $row['unit_cost'],
                    'total' => $line,
                ];
            }

            $po = $this->purchaseOrders->create(
                [
                    'po_number' => $this->purchaseOrders->nextPoNumber(),
                    'supplier_id' => $data['supplier_id'],
                    'branch_id' => $data['branch_id'],
                    'description' => $data['description'] ?? null,
                    'total_amount' => $calculated,
                    'amount_paid' => '0',
                    'payment_type' => $data['payment_type'],
                    'status' => PurchaseOrderStatus::Pending->value,
                    'received_at' => null,
                    'created_by' => $user->id,
                ],
                $items
            );

            $supplier->total_debt = bcadd((string) $supplier->total_debt, $calculated, 2);
            $supplier->save();

            if ($data['payment_type'] === PurchasePaymentType::Installments->value) {
                $n = max(1, (int) ($data['installment_count'] ?? 1));
                $each = bcdiv($calculated, (string) $n, 2);
                for ($i = 1; $i <= $n; $i++) {
                    SupplierInstallment::query()->create([
                        'po_id' => $po->id,
                        'supplier_id' => $supplier->id,
                        'installment_no' => $i,
                        'amount' => $each,
                        'due_date' => isset($data['installment_start_date'])
                            ? Carbon::parse($data['installment_start_date'])->addMonths($i - 1)->toDateString()
                            : now()->addMonths($i - 1)->toDateString(),
                        'is_paid' => false,
                        'paid_at' => null,
                        'payment_method' => null,
                        'paid_by' => null,
                        'notes' => null,
                        'created_at' => now(),
                    ]);
                }
            } else {
                SupplierInstallment::query()->create([
                    'po_id' => $po->id,
                    'supplier_id' => $supplier->id,
                    'installment_no' => 1,
                    'amount' => $calculated,
                    'due_date' => now()->toDateString(),
                    'is_paid' => false,
                    'paid_at' => null,
                    'payment_method' => null,
                    'paid_by' => null,
                    'notes' => null,
                    'created_at' => now(),
                ]);
            }

            $fresh = $this->purchaseOrders->findWithRelations($po->id);
            $this->audit->record($user, 'purchase_order.create', 'purchase_order', $po->id, null, $fresh?->toArray());

            return $po->fresh(['items', 'installments']);
        });
    }

    public function receive(User $user, PurchaseOrder $po): PurchaseOrder
    {
        if ($po->received_at !== null) {
            throw new \InvalidArgumentException('Purchase order already received.');
        }

        DB::transaction(function () use ($user, $po) {
            $po->load('items');
            foreach ($po->items as $item) {
                $stock = $this->stock->firstOrCreate($item->part_id, $po->branch_id);
                $this->stock->adjustQuantity($stock, $item->quantity);
                $this->movements->create([
                    'part_id' => $item->part_id,
                    'branch_id' => $po->branch_id,
                    'movement_type' => StockMovementType::PurchaseIn,
                    'quantity' => $item->quantity,
                    'reference_id' => $po->id,
                    'reference_type' => 'purchase_order',
                    'notes' => null,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);
            }
            $po->received_at = now();
            $po->save();
        });

        $this->dashboardCache->forgetSummary();

        return $po->fresh(['items']);
    }

    public function cancel(User $user, PurchaseOrder $po): void
    {
        if ($po->received_at) {
            throw new \InvalidArgumentException('Cannot cancel a received purchase order.');
        }
        if (bccomp((string) $po->amount_paid, '0', 2) > 0) {
            throw new \InvalidArgumentException('Cannot cancel a purchase order with payments.');
        }

        $before = $this->purchaseOrders->findWithRelations($po->id)?->toArray();

        DB::transaction(function () use ($po) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($po->supplier_id);
            $supplier->total_debt = bcsub((string) $supplier->total_debt, (string) $po->total_amount, 2);
            $supplier->save();
            $po->installments()->delete();
            $po->delete();
        });

        $this->audit->record($user, 'purchase_order.cancel', 'purchase_order', $po->id, $before, null);
        $this->dashboardCache->forgetSummary();
    }
}
