<?php

namespace App\Transformers;

use App\Models\Invoice;

final class CustomerBalanceTransformer
{
    /**
     * @param  array{outstanding_balance?: float|int|string, unpaid_invoices?: iterable<Invoice>}  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'outstanding_balance' => (float) ($payload['outstanding_balance'] ?? 0),
            'unpaid_invoices' => collect($payload['unpaid_invoices'] ?? [])
                ->map(fn ($inv) => InvoiceTransformer::transform($inv))
                ->values()
                ->all(),
        ];
    }
}
