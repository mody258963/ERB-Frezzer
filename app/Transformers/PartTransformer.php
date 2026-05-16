<?php

namespace App\Transformers;

use App\Models\Part;
use App\Transformers\Concerns\TransformsBackedEnums;

final class PartTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(Part $part): array
    {
        return [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
            'category' => self::enumValue($part->category),
            'unit' => $part->unit,
            'sell_price' => (float) $part->sell_price,
            'cost_price' => (float) $part->cost_price,
            'min_stock' => (int) $part->min_stock,
            'is_active' => $part->is_active,
            'created_at' => $part->created_at?->toISOString(),
            'updated_at' => $part->updated_at?->toISOString(),
        ];
    }
}
