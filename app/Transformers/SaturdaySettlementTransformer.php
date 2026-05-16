<?php

namespace App\Transformers;

use App\Models\SaturdaySettlement;
use App\Transformers\Concerns\TransformsBackedEnums;

final class SaturdaySettlementTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(SaturdaySettlement $settlement): array
    {
        $data = [
            'id' => $settlement->id,
            'settlement_date' => $settlement->settlement_date?->format('Y-m-d'),
            'customer_id' => $settlement->customer_id,
            'total_amount' => (float) $settlement->total_amount,
            'payment_method' => self::enumValue($settlement->payment_method),
            'notes' => $settlement->notes,
            'created_by' => $settlement->created_by,
            'created_at' => $settlement->created_at?->toISOString(),
        ];

        if ($settlement->relationLoaded('customer') && $settlement->customer) {
            $data['customer'] = CustomerTransformer::transform($settlement->customer);
        }

        if ($settlement->relationLoaded('creator') && $settlement->creator) {
            $data['creator'] = UserTransformer::transform($settlement->creator);
        }

        if ($settlement->relationLoaded('invoices')) {
            $data['invoices'] = $settlement->invoices
                ->map(fn ($inv) => InvoiceTransformer::transform($inv))
                ->values()
                ->all();
        }

        return $data;
    }
}
