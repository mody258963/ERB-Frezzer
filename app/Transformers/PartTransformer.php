<?php

namespace App\Transformers;

use App\Models\Part;
use App\Services\PartImageService;
use App\Transformers\Concerns\TransformsBackedEnums;

final class PartTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(Part $part): array
    {
        $data = [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
            'category_id' => $part->category_id,
            'unit' => self::enumValue($part->unit),
            'unit_label' => $part->unit?->label(),
            'sell_price' => (float) $part->sell_price,
            'cost_price' => (float) $part->cost_price,
            'min_stock' => (int) $part->min_stock,
            'is_active' => $part->is_active,
            'branch_id' => $part->branch_id,
            'image_url' => app(PartImageService::class)->url($part->image_path),
            'created_at' => $part->created_at?->toISOString(),
            'updated_at' => $part->updated_at?->toISOString(),
        ];

        if ($part->relationLoaded('category') && $part->category) {
            $data['category'] = PartCategoryTransformer::transform($part->category);
            $data['category_key'] = $part->category->key;
            $data['category_name'] = $part->category->name;
        }

        return $data;
    }
}
