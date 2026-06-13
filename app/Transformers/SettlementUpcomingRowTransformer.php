<?php

namespace App\Transformers;

final class SettlementUpcomingRowTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(object $r): array
    {
        return [
            'customer_id' => $r->customer_id ?? null,
            'customer_name' => $r->customer_name ?? null,
            'amount_due' => isset($r->amount_due) ? (float) $r->amount_due : null,
            'settlement_cycle' => $r->settlement_cycle ?? 'weekly',
        ];
    }
}
