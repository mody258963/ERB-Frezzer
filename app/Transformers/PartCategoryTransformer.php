<?php

namespace App\Transformers;

use App\Models\PartCategory;

final class PartCategoryTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(PartCategory $category): array
    {
        return [
            'id' => $category->id,
            'key' => $category->key,
            'name' => $category->name,
            'sort_order' => (int) $category->sort_order,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }
}
