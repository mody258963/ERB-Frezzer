<?php

namespace App\Transformers;

final class InventoryValuationRowTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        return [
            'part_id' => $row['part_id'] ?? null,
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'quantity' => (float) ($row['quantity'] ?? 0),
            'value_cost' => (float) ($row['value_cost'] ?? 0),
            'value_sell' => (float) ($row['value_sell'] ?? 0),
        ];
    }
}
