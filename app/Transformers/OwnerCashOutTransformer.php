<?php

namespace App\Transformers;

use App\Models\OwnerCashOut;

final class OwnerCashOutTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(OwnerCashOut $row): array
    {
        return [
            'id' => $row->id,
            'amount' => (float) $row->amount,
            'reason' => $row->reason,
            'notes' => $row->notes,
            'created_by' => $row->creator ? [
                'id' => $row->creator->id,
                'name' => $row->creator->name,
            ] : null,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
