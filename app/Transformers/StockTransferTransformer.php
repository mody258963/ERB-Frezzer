<?php

namespace App\Transformers;

use App\Models\StockTransfer;
use App\Transformers\Concerns\TransformsBackedEnums;

final class StockTransferTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(StockTransfer $transfer): array
    {
        $data = [
            'id' => $transfer->id,
            'from_branch_id' => $transfer->from_branch_id,
            'to_branch_id' => $transfer->to_branch_id,
            'status' => self::enumValue($transfer->status),
            'notes' => $transfer->notes,
            'created_by' => $transfer->created_by,
            'created_at' => $transfer->created_at?->toISOString(),
            'updated_at' => $transfer->updated_at?->toISOString(),
        ];

        if ($transfer->relationLoaded('fromBranch') && $transfer->fromBranch) {
            $data['from_branch'] = BranchTransformer::transform($transfer->fromBranch);
        }

        if ($transfer->relationLoaded('toBranch') && $transfer->toBranch) {
            $data['to_branch'] = BranchTransformer::transform($transfer->toBranch);
        }

        if ($transfer->relationLoaded('creator') && $transfer->creator) {
            $data['creator'] = UserTransformer::transform($transfer->creator);
        }

        if ($transfer->relationLoaded('items')) {
            $data['items'] = $transfer->items
                ->map(fn ($item) => StockTransferItemTransformer::transform($item))
                ->values()
                ->all();
        }

        return $data;
    }
}
