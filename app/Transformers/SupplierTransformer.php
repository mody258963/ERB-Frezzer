<?php

namespace App\Transformers;

use App\Models\Supplier;

final class SupplierTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'total_debt' => (float) $supplier->total_debt,
            'is_active' => $supplier->is_active,
            'created_at' => $supplier->created_at?->toISOString(),
            'updated_at' => $supplier->updated_at?->toISOString(),
        ];
    }
}
