<?php

namespace App\Transformers;

final class SupplierDebtAgingRowTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(object $r): array
    {
        return [
            'supplier_id' => $r->id ?? null,
            'name' => $r->name ?? null,
            'total_debt' => isset($r->total_debt) ? (float) $r->total_debt : null,
            'updated_at' => isset($r->updated_at) ? (string) $r->updated_at : null,
        ];
    }
}
