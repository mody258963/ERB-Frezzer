<?php

namespace App\Transformers;

use App\Models\SupplierInstallment;
use App\Transformers\Concerns\TransformsBackedEnums;

final class SupplierInstallmentTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(SupplierInstallment $installment): array
    {
        $data = [
            'id' => $installment->id,
            'po_id' => $installment->po_id,
            'supplier_id' => $installment->supplier_id,
            'installment_no' => (int) $installment->installment_no,
            'amount' => (float) $installment->amount,
            'due_date' => $installment->due_date?->format('Y-m-d'),
            'is_paid' => $installment->is_paid,
            'paid_at' => $installment->paid_at?->toISOString(),
            'payment_method' => self::enumValue($installment->payment_method),
            'paid_by' => $installment->paid_by,
            'notes' => $installment->notes,
            'created_at' => $installment->created_at?->toISOString(),
        ];

        if ($installment->relationLoaded('supplier') && $installment->supplier) {
            $data['supplier'] = SupplierTransformer::transform($installment->supplier);
        }

        if ($installment->relationLoaded('purchaseOrder') && $installment->purchaseOrder) {
            $data['purchase_order'] = PurchaseOrderSummaryTransformer::transform($installment->purchaseOrder);
        }

        if ($installment->relationLoaded('paidByUser') && $installment->paidByUser) {
            $data['paid_by_user'] = UserTransformer::transform($installment->paidByUser);
        }

        return $data;
    }
}
