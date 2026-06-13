<?php

namespace App\Transformers;

final class DashboardInventoryRowTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        return [
            'part_id' => $row['part_id'] ?? null,
            'part_code' => $row['part_code'] ?? null,
            'branch_id' => $row['branch_id'] ?? null,
            'branch_name' => $row['branch_name'] ?? null,
            'quantity' => (float) ($row['quantity'] ?? 0),
            'average_cost' => (float) ($row['average_cost'] ?? 0),
            'value_at_cost' => (float) ($row['value_at_cost'] ?? 0),
            'min_stock' => isset($row['min_stock']) ? (int) $row['min_stock'] : null,
            'low' => (bool) ($row['low'] ?? false),
        ];
    }
}
