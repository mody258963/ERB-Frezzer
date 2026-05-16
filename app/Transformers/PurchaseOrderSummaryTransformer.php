<?php

namespace App\Transformers;

use App\Models\PurchaseOrder;
use App\Transformers\Concerns\TransformsBackedEnums;

final class PurchaseOrderSummaryTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'branch_id' => $po->branch_id,
            'total_amount' => (float) $po->total_amount,
            'amount_paid' => (float) $po->amount_paid,
            'payment_type' => self::enumValue($po->payment_type),
            'status' => self::enumValue($po->status),
            'received_at' => $po->received_at?->toISOString(),
            'created_at' => $po->created_at?->toISOString(),
        ];
    }
}
