<?php

namespace App\Transformers;

final class FinancialReportTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        $period = $payload['period'] ?? [];
        $totals = $payload['totals'] ?? [];
        $returns = $payload['returns'] ?? [];

        return [
            'period' => [
                'from' => $period['from'] ?? null,
                'to' => $period['to'] ?? null,
                'branch_id' => $period['branch_id'] ?? null,
            ],
            'totals' => [
                'revenue' => (float) ($totals['revenue'] ?? 0),
                'discount' => (float) ($totals['discount'] ?? 0),
                'customer_refunds' => (float) ($totals['customer_refunds'] ?? 0),
                'net_sales' => (float) ($totals['net_sales'] ?? 0),
                'gross_profit' => (float) ($totals['gross_profit'] ?? 0),
                'profit' => (float) ($totals['profit'] ?? 0),
            ],
            'returns' => [
                'customer_count' => (int) ($returns['customer_count'] ?? 0),
                'customer_value' => (float) ($returns['customer_value'] ?? 0),
                'supplier_count' => (int) ($returns['supplier_count'] ?? 0),
                'supplier_value' => (float) ($returns['supplier_value'] ?? 0),
            ],
            'by_branch' => collect($payload['by_branch'] ?? [])->map(fn ($row) => [
                'branch_id' => $row['branch_id'] ?? null,
                'name' => $row['name'] ?? null,
                'revenue' => (float) ($row['revenue'] ?? 0),
                'discount' => (float) ($row['discount'] ?? 0),
                'customer_refunds' => (float) ($row['customer_refunds'] ?? 0),
                'gross_profit' => (float) ($row['gross_profit'] ?? 0),
                'profit' => (float) ($row['profit'] ?? 0),
            ])->values()->all(),
        ];
    }
}
