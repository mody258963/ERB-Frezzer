<?php

namespace App\Transformers;

final class CustomerAgingReportRowTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(object $r): array
    {
        return [
            'customer_id' => $r->id ?? null,
            'name' => $r->name ?? null,
            'outstanding_balance' => isset($r->outstanding_balance) ? (float) $r->outstanding_balance : null,
            'oldest_invoice_at' => isset($r->oldest_invoice) ? (string) $r->oldest_invoice : null,
        ];
    }
}
