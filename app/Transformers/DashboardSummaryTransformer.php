<?php

namespace App\Transformers;

final class DashboardSummaryTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        return [
            'total_receivables' => (float) ($row['total_receivables'] ?? 0),
            'total_supplier_debt' => (float) ($row['total_supplier_debt'] ?? 0),
            'total_stock_value_cost' => (float) ($row['total_stock_value_cost'] ?? 0),
            'weekly_revenue' => (float) ($row['weekly_revenue'] ?? 0),
            'weekly_profit' => (float) ($row['weekly_profit'] ?? 0),
        ];
    }
}
