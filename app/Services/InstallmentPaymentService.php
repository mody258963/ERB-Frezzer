<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SettlementPaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInstallmentPayment;
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
     * @param  array{payment_method: string, notes?: ?string, amount?: float|string|null}  $data
     */
    public function pay(User $user, SupplierInstallment $installment, array $data): SupplierInstallment
    {
        $paymentAmount = $this->resolvePaymentAmount($installment, $data['amount'] ?? null);

        if (bccomp($paymentAmount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        DB::transaction(function () use ($user, $installment, $data, $paymentAmount) {
            $beforeSnap = SupplierInstallment::query()->findOrFail($installment->id)->toArray();

            $inst = SupplierInstallment::query()->lockForUpdate()->findOrFail($installment->id);
            $balance = $inst->balanceDue();

            if (bccomp($balance, '0', 2) <= 0) {
                throw new \InvalidArgumentException('Installment already fully paid.');
            }

            if (bccomp($paymentAmount, $balance, 2) > 0) {
                throw new \InvalidArgumentException(
                    'Payment amount exceeds installment balance due ('.$balance.').',
                );
            }

            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($inst->po_id);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($inst->supplier_id);

            $method = SettlementPaymentMethod::from($data['payment_method']);
            $paidAt = now();

            SupplierInstallmentPayment::query()->create([
                'installment_id' => $inst->id,
                'supplier_id' => $inst->supplier_id,
                'po_id' => $inst->po_id,
                'amount' => $paymentAmount,
                'payment_method' => $method,
                'paid_by' => $user->id,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $paidAt,
            ]);

            $inst->amount_paid = bcadd((string) $inst->amount_paid, $paymentAmount, 2);
            $inst->payment_method = $method;
            $inst->paid_by = $user->id;
            $inst->notes = $data['notes'] ?? $inst->notes;

            if (bccomp($inst->balanceDue(), '0', 2) <= 0) {
                $inst->is_paid = true;
                $inst->paid_at = $paidAt;
            } else {
                $inst->is_paid = false;
                $inst->paid_at = null;
            }

            $inst->save();

            $po->amount_paid = bcadd((string) $po->amount_paid, $paymentAmount, 2);

            if (bccomp((string) $po->amount_paid, (string) $po->total_amount, 2) >= 0) {
                $po->status = PurchaseOrderStatus::Settled->value;
            } elseif (bccomp((string) $po->amount_paid, '0', 2) > 0) {
                $po->status = PurchaseOrderStatus::Partial->value;
            }

            $po->save();

            $supplier->total_debt = bcsub((string) $supplier->total_debt, $paymentAmount, 2);
            if (bccomp((string) $supplier->total_debt, '0', 2) < 0) {
                $supplier->total_debt = '0.00';
            }
            $supplier->save();

            $this->audit->record(
                $user,
                'installment.pay',
                'supplier_installment',
                $inst->id,
                $beforeSnap,
                array_merge($inst->fresh()->toArray(), ['payment_amount' => $paymentAmount]),
            );
        });

        $this->dashboardCache->forgetAllSummaries();

        return SupplierInstallment::query()
            ->with(['supplier', 'purchaseOrder', 'paidByUser'])
            ->findOrFail($installment->id);
    }

    private function resolvePaymentAmount(SupplierInstallment $installment, mixed $requested): string
    {
        $balance = $installment->balanceDue();

        if ($requested === null || $requested === '') {
            return $balance;
        }

        return bcadd((string) $requested, '0', 2);
    }
}
