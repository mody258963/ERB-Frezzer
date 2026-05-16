<?php

namespace App\Transformers;

use App\Models\InvoiceItem;

final class InvoiceItemTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(InvoiceItem $item): array
    {
        $data = [
            'id' => $item->id,
            'invoice_id' => $item->invoice_id,
            'part_id' => $item->part_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->total,
        ];

        if ($item->relationLoaded('part') && $item->part) {
            $data['part'] = PartTransformer::transform($item->part);
        }

        return $data;
    }
}
