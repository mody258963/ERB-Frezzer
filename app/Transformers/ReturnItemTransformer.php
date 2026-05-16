<?php

namespace App\Transformers;

use App\Models\ReturnItem;
use App\Transformers\Concerns\TransformsBackedEnums;

final class ReturnItemTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(ReturnItem $item): array
    {
        $data = [
            'id' => $item->id,
            'return_id' => $item->return_id,
            'part_id' => $item->part_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'condition' => self::enumValue($item->condition),
            'total' => (float) $item->total,
        ];

        if ($item->relationLoaded('part') && $item->part) {
            $data['part'] = PartTransformer::transform($item->part);
        }

        return $data;
    }
}
