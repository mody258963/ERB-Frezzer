<?php

namespace App\Transformers;

final class ReturnsSummaryTransformer
{
    /**
     * @param  array{total_count?: int, total_value?: float|int|string, by_reason?: iterable}  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'total_count' => (int) ($payload['total_count'] ?? 0),
            'total_value' => (float) ($payload['total_value'] ?? 0),
            'by_reason' => collect($payload['by_reason'] ?? [])->map(fn ($row) => [
                'reason' => $row->reason ?? null,
                'count' => isset($row->c) ? (int) $row->c : null,
            ])->values()->all(),
        ];
    }
}
