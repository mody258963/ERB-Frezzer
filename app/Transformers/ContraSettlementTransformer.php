<?php

namespace App\Transformers;

use App\Models\ContraSettlement;

final class ContraSettlementTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(ContraSettlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'customer_id' => $settlement->customer_id,
            'supplier_id' => $settlement->supplier_id,
            'amount' => (float) $settlement->amount,
            'notes' => $settlement->notes,
            'customer' => $settlement->relationLoaded('customer') && $settlement->customer ? [
                'id' => $settlement->customer->id,
                'name' => $settlement->customer->name,
            ] : null,
            'supplier' => $settlement->relationLoaded('supplier') && $settlement->supplier ? [
                'id' => $settlement->supplier->id,
                'name' => $settlement->supplier->name,
            ] : null,
            'created_by' => $settlement->creator ? [
                'id' => $settlement->creator->id,
                'name' => $settlement->creator->name,
            ] : null,
            'created_at' => $settlement->created_at?->toIso8601String(),
        ];
    }
}
