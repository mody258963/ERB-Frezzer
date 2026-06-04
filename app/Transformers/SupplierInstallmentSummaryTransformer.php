<?php

namespace App\Transformers;

use App\Models\SupplierInstallment;
use App\Transformers\Concerns\TransformsBackedEnums;

final class SupplierInstallmentSummaryTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(SupplierInstallment $installment): array
    {
        return [
            'id' => $installment->id,
            'po_id' => $installment->po_id,
            'supplier_id' => $installment->supplier_id,
            'installment_no' => (int) $installment->installment_no,
            'amount' => (float) $installment->amount,
            'amount_paid' => (float) $installment->amount_paid,
            'balance_due' => (float) $installment->balanceDue(),
            'due_date' => $installment->due_date?->format('Y-m-d'),
            'is_paid' => $installment->is_paid,
            'paid_at' => $installment->paid_at?->toISOString(),
            'payment_method' => self::enumValue($installment->payment_method),
            'paid_by' => $installment->paid_by,
            'notes' => $installment->notes,
            'created_at' => $installment->created_at?->toISOString(),
        ];
    }
}
