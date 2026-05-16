<?php

namespace App\Transformers;

final class DashboardSalesTransformer
{
    /**
     * @param  array{by_category?: iterable, by_branch?: iterable, credit_vs_cash?: iterable}  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'by_category' => collect($payload['by_category'] ?? [])->map(fn ($row) => [
                'category' => $row->category ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
            ])->values()->all(),
            'by_branch' => collect($payload['by_branch'] ?? [])->map(fn ($row) => [
                'name' => $row->name ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
            ])->values()->all(),
            'credit_vs_cash' => collect($payload['credit_vs_cash'] ?? [])->map(fn ($row) => [
                'payment_type' => $row->payment_type ?? null,
                'total' => isset($row->total) ? (float) $row->total : null,
            ])->values()->all(),
        ];
    }
}
