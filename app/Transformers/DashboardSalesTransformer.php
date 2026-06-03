<?php

namespace App\Transformers;

final class DashboardSalesTransformer
{
    /**
     * @param  array{
     *     totals?: array<string, float>,
     *     by_category?: iterable,
     *     by_branch?: iterable,
     *     credit_vs_cash?: iterable
     * }  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        $totals = $payload['totals'] ?? [];

        return [
            'totals' => [
                'revenue' => (float) ($totals['revenue'] ?? 0),
                'discount' => (float) ($totals['discount'] ?? 0),
                'customer_refunds' => (float) ($totals['customer_refunds'] ?? 0),
                'net_sales' => (float) ($totals['net_sales'] ?? 0),
                'gross_profit' => (float) ($totals['gross_profit'] ?? 0),
                'profit' => (float) ($totals['profit'] ?? 0),
            ],
            'by_category' => collect($payload['by_category'] ?? [])->map(fn ($row) => [
                'category_key' => $row->category_key ?? null,
                'category' => $row->category ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
            ])->values()->all(),
            'by_branch' => collect($payload['by_branch'] ?? [])->map(fn ($row) => [
                'branch_id' => $row->branch_id ?? null,
                'name' => $row->name ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
                'revenue' => isset($row->revenue) ? (float) $row->revenue : null,
                'discount' => isset($row->discount) ? (float) $row->discount : null,
                'customer_refunds' => isset($row->customer_refunds) ? (float) $row->customer_refunds : null,
                'profit' => isset($row->profit) ? (float) $row->profit : null,
            ])->values()->all(),
            'credit_vs_cash' => collect($payload['credit_vs_cash'] ?? [])->map(fn ($row) => [
                'payment_type' => $row->payment_type ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
            ])->values()->all(),
        ];
    }
}
