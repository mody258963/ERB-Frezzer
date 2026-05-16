<?php

namespace App\Transformers;

use App\Models\PurchaseOrder;
use App\Transformers\Concerns\TransformsBackedEnums;

final class PurchaseOrderTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(PurchaseOrder $po): array
    {
        $data = [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'branch_id' => $po->branch_id,
            'description' => $po->description,
            'total_amount' => (float) $po->total_amount,
            'amount_paid' => (float) $po->amount_paid,
            'payment_type' => self::enumValue($po->payment_type),
            'status' => self::enumValue($po->status),
            'received_at' => $po->received_at?->toISOString(),
            'created_by' => $po->created_by,
            'created_at' => $po->created_at?->toISOString(),
            'updated_at' => $po->updated_at?->toISOString(),
        ];

        if ($po->relationLoaded('supplier') && $po->supplier) {
            $data['supplier'] = SupplierTransformer::transform($po->supplier);
        }

        if ($po->relationLoaded('branch') && $po->branch) {
            $data['branch'] = BranchTransformer::transform($po->branch);
        }

        if ($po->relationLoaded('creator') && $po->creator) {
            $data['creator'] = UserTransformer::transform($po->creator);
        }

        if ($po->relationLoaded('items')) {
            $data['items'] = $po->items
                ->map(fn ($item) => PurchaseOrderItemTransformer::transform($item))
                ->values()
                ->all();
        }

        if ($po->relationLoaded('installments')) {
            $data['installments'] = $po->installments
                ->map(fn ($i) => SupplierInstallmentSummaryTransformer::transform($i))
                ->values()
                ->all();
        }

        return $data;
    }
}
