<?php

namespace App\Transformers;

final class DashboardPayablesTransformer
{
    /**
     * @param  array{upcoming_30_days?: iterable, overdue?: iterable}  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'upcoming_30_days' => collect($payload['upcoming_30_days'] ?? [])
                ->map(fn ($i) => SupplierInstallmentTransformer::transform($i))
                ->values()
                ->all(),
            'overdue' => collect($payload['overdue'] ?? [])
                ->map(fn ($i) => SupplierInstallmentTransformer::transform($i))
                ->values()
                ->all(),
        ];
    }
}
