<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SettlementPaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInstallmentPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SupplierInstallmentAllocationService
{
    /**
     * Allocate a lump-sum payment across unpaid installments (oldest due first).
     *
     * @return list<SupplierInstallmentPayment>
     */
    public function allocate(
        User $user,
        Supplier $supplier,
        string $amount,
        SettlementPaymentMethod $method,
        ?string $notes = null,
        ?CarbonInterface $paidAt = null,
    ): array {
        $remaining = $amount;
        $paidAt = $paidAt ?? now();
        $created = [];

        $installments = SupplierInstallment::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_paid', false)
            ->orderBy('due_date')
            ->orderBy('installment_no')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $inst) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $due = $inst->balanceDue();
            if (bccomp($due, '0', 2) <= 0) {
                continue;
            }

            $apply = bccomp($remaining, $due, 2) >= 0 ? $due : $remaining;

            $payment = SupplierInstallmentPayment::query()->create([
                'installment_id' => $inst->id,
                'supplier_id' => $supplier->id,
                'po_id' => $inst->po_id,
                'amount' => $apply,
                'payment_method' => $method,
                'paid_by' => $user->id,
                'notes' => $notes,
                'paid_at' => $paidAt,
            ]);

            $inst->amount_paid = bcadd((string) $inst->amount_paid, $apply, 2);
            $inst->payment_method = $method;
            $inst->paid_by = $user->id;
            $inst->notes = $notes;

            if (bccomp($inst->balanceDue(), '0', 2) <= 0) {
                $inst->is_paid = true;
                $inst->paid_at = $paidAt;
            } else {
                $inst->is_paid = false;
                $inst->paid_at = null;
            }

            $inst->save();

            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($inst->po_id);
            $po->amount_paid = bcadd((string) $po->amount_paid, $apply, 2);

            if (bccomp((string) $po->amount_paid, (string) $po->total_amount, 2) >= 0) {
                $po->status = PurchaseOrderStatus::Settled->value;
            } elseif (bccomp((string) $po->amount_paid, '0', 2) > 0) {
                $po->status = PurchaseOrderStatus::Partial->value;
            }

            $po->save();
            $remaining = bcsub($remaining, $apply, 2);
            $created[] = $payment;
        }

        if (bccomp($remaining, '0', 2) > 0) {
            throw new \InvalidArgumentException(
                'Payment amount exceeds unpaid supplier installments ('.number_format((float) bcsub($amount, $remaining, 2), 2).' allocatable).',
            );
        }

        $supplier->total_debt = bcsub((string) $supplier->total_debt, $amount, 2);
        if (bccomp((string) $supplier->total_debt, '0', 2) < 0) {
            $supplier->total_debt = '0.00';
        }
        $supplier->save();

        return $created;
    }

    /**
     * @return Collection<int, SupplierInstallmentPayment>
     */
    public function paymentHistory(string $supplierId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return SupplierInstallmentPayment::query()
            ->where('supplier_id', $supplierId)
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset)
            ->with(['installment'])
            ->orderByDesc('paid_at')
            ->paginate($perPage);
    }
}
