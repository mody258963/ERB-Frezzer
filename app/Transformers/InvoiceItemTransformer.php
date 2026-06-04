<?php

namespace App\Transformers;

use App\Models\InvoiceItem;

final class InvoiceItemTransformer
{
    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, array{
     *     quantity_sold: int,
     *     quantity_returned_completed: int,
     *     quantity_returned_pending: int,
     *     quantity_available: int
     * }>|null  $returnQuantitiesByPart
     */
    public static function transform(InvoiceItem $item, ?array $returnQuantitiesByPart = null): array
    {
        $data = [
            'id' => $item->id,
            'invoice_id' => $item->invoice_id,
            'part_id' => $item->part_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->total,
        ];

        if ($returnQuantitiesByPart !== null) {
            $stats = $returnQuantitiesByPart[$item->part_id] ?? [
                'quantity_sold' => (int) $item->quantity,
                'quantity_returned_completed' => 0,
                'quantity_returned_pending' => 0,
                'quantity_available' => (int) $item->quantity,
            ];
            $data['quantity_sold'] = $stats['quantity_sold'];
            $data['quantity_returned_completed'] = $stats['quantity_returned_completed'];
            $data['quantity_returned_pending'] = $stats['quantity_returned_pending'];
            $data['quantity_available_for_return'] = $stats['quantity_available'];
            $data['quantity_remaining'] = max(
                0,
                $stats['quantity_sold']
                    - $stats['quantity_returned_completed']
                    - $stats['quantity_returned_pending']
            );
        }

        if ($item->relationLoaded('part') && $item->part) {
            $data['part'] = PartTransformer::transform($item->part);
        }

        return $data;
    }
}
