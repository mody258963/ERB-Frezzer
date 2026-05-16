<?php

namespace App\Transformers;

use App\Models\StockTransferItem;

final class StockTransferItemTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(StockTransferItem $item): array
    {
        $data = [
            'id' => $item->id,
            'transfer_id' => $item->transfer_id,
            'part_id' => $item->part_id,
            'quantity' => (int) $item->quantity,
        ];

        if ($item->relationLoaded('part') && $item->part) {
            $data['part'] = PartTransformer::transform($item->part);
        }

        return $data;
    }
}
