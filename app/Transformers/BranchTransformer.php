<?php

namespace App\Transformers;

use App\Models\Branch;

final class BranchTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_active' => $branch->is_active,
            'created_at' => $branch->created_at?->toISOString(),
            'updated_at' => $branch->updated_at?->toISOString(),
        ];
    }
}
