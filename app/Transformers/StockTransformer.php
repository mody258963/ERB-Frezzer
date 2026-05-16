<?php

namespace App\Transformers;

use App\Models\Stock;

final class StockTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(Stock $stock): array
    {
        $data = [
            'id' => $stock->id,
            'part_id' => $stock->part_id,
            'branch_id' => $stock->branch_id,
            'quantity' => (int) $stock->quantity,
            'created_at' => $stock->created_at?->toISOString(),
            'updated_at' => $stock->updated_at?->toISOString(),
        ];

        if ($stock->relationLoaded('part') && $stock->part) {
            $data['part'] = PartTransformer::transform($stock->part);
        }

        if ($stock->relationLoaded('branch') && $stock->branch) {
            $data['branch'] = BranchTransformer::transform($stock->branch);
        }

        return $data;
    }
}
