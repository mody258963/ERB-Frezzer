<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SettlementPaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InstallmentPaymentService
{
    public function __construct(
        private SupplierInstallmentRepositoryInterface $installments,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @param  array{payment_method: string, notes?: ?string}  $data
     */
    public function pay(User $user, SupplierInstallment $installment, array $data): SupplierInstallment
    {
        if ($installment->is_paid) {
            throw new \InvalidArgumentException('Installment already paid.');
        }

        DB::transaction(function () use ($user, $installment, $data) {
            $beforeSnap = SupplierInstallment::query()->findOrFail($installment->id)->toArray();

            $inst = SupplierInstallment::query()->lockForUpdate()->findOrFail($installment->id);
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($inst->po_id);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($inst->supplier_id);

            $amount = (string) $inst->amount;

            $inst->is_paid = true;
            $inst->paid_at = now();
            $inst->payment_method = SettlementPaymentMethod::from($data['payment_method']);
            $inst->paid_by = $user->id;
            $inst->notes = $data['notes'] ?? null;
            $inst->save();

            $po->amount_paid = bcadd((string) $po->amount_paid, $amount, 2);

            if (bccomp((string) $po->amount_paid, (string) $po->total_amount, 2) >= 0) {
                $po->status = PurchaseOrderStatus::Settled->value;
            } elseif (bccomp((string) $po->amount_paid, '0', 2) > 0) {
                $po->status = PurchaseOrderStatus::Partial->value;
            }

            $po->save();

            $supplier->total_debt = bcsub((string) $supplier->total_debt, $amount, 2);
            $supplier->save();

            $this->audit->record($user, 'installment.pay', 'supplier_installment', $inst->id, $beforeSnap, $inst->fresh()->toArray());
        });

        $this->dashboardCache->forgetAllSummaries();

        return SupplierInstallment::query()
            ->with(['supplier', 'purchaseOrder'])
            ->findOrFail($installment->id);
    }
}
