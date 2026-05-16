<?php

namespace App\Transformers;

final class LowStockAlertTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(object $r): array
    {
        return [
            'part_id' => $r->part_id ?? null,
            'branch_id' => $r->branch_id ?? null,
            'quantity' => isset($r->quantity) ? (int) $r->quantity : null,
            'min_stock' => isset($r->min_stock) ? (int) $r->min_stock : null,
            'part_name' => $r->name ?? null,
        ];
    }
}
