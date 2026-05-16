<?php

namespace App\Transformers;

use App\Models\PurchaseOrderItem;

final class PurchaseOrderItemTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(PurchaseOrderItem $item): array
    {
        $data = [
            'id' => $item->id,
            'po_id' => $item->po_id,
            'part_id' => $item->part_id,
            'quantity' => (int) $item->quantity,
            'unit_cost' => (float) $item->unit_cost,
            'total' => (float) $item->total,
        ];

        if ($item->relationLoaded('part') && $item->part) {
            $data['part'] = PartTransformer::transform($item->part);
        }

        return $data;
    }
}
