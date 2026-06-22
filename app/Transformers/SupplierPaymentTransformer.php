<?php

namespace App\Transformers;

use App\Models\SupplierInstallmentPayment;

final class SupplierPaymentTransformer
{
    /**
     * @param  array{
     *     supplier: \App\Models\Supplier,
     *     amount: string,
     *     payment_method: \App\Enums\SettlementPaymentMethod,
     *     notes: ?string,
     *     paid_at: \Carbon\CarbonInterface,
     *     allocations: list<SupplierInstallmentPayment>
     * }  $result
     * @return array<string, mixed>
     */
    public static function transformPayResult(array $result): array
    {
        return [
            'supplier_id' => $result['supplier']->id,
            'supplier_name' => $result['supplier']->name,
            'amount' => (float) $result['amount'],
            'payment_method' => $result['payment_method']->value,
            'notes' => $result['notes'],
            'paid_at' => $result['paid_at']->toIso8601String(),
            'total_debt_after' => (float) $result['supplier']->total_debt,
            'allocations' => array_map(
                fn (SupplierInstallmentPayment $p) => self::transformAllocation($p),
                $result['allocations'],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transformAllocation(SupplierInstallmentPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'installment_id' => $payment->installment_id,
            'installment_no' => $payment->installment?->installment_no,
            'po_id' => $payment->po_id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'notes' => $payment->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transformHistoryRow(SupplierInstallmentPayment $payment): array
    {
        return self::transformAllocation($payment);
    }
}
