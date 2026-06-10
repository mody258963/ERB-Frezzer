<?php

namespace App\Transformers;

use App\Models\CapitalAdjustment;

final class CapitalAdjustmentTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(CapitalAdjustment $row): array
    {
        return [
            'id' => $row->id,
            'branch_id' => $row->branch_id,
            'branch' => $row->branch ? [
                'id' => $row->branch->id,
                'name' => $row->branch->name,
            ] : null,
            'type' => $row->type?->value ?? 'manual_set',
            'previous_amount' => (float) $row->previous_amount,
            'new_amount' => (float) $row->new_amount,
            'change_amount' => (float) $row->change_amount,
            'reason' => $row->reason,
            'created_by' => $row->creator ? [
                'id' => $row->creator->id,
                'name' => $row->creator->name,
            ] : null,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
