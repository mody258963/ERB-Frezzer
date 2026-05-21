<?php

namespace App\Support;

use App\Models\PartCategory;
use Illuminate\Validation\ValidationException;

final class PartLookupResolver
{
    public static function resolveCategoryId(array $data): string
    {
        $categoryId = $data['category_id'] ?? null;
        if (! $categoryId && ! empty($data['category_key'])) {
            $categoryId = PartCategory::query()
                ->where('key', $data['category_key'])
                ->where('is_active', true)
                ->value('id');
        }

        if (! $categoryId) {
            throw ValidationException::withMessages([
                'category_id' => ['A valid category_id or category_key is required.'],
            ]);
        }

        return (string) $categoryId;
    }
}
