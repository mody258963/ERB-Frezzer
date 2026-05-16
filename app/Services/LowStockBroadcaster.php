<?php

namespace App\Services;

use App\Models\Part;
use App\Models\Stock;
use Illuminate\Support\Facades\Redis;

class LowStockBroadcaster
{
    public function notifyIfNeeded(string $partId, string $branchId): void
    {
        $part = Part::query()->find($partId);
        if (! $part) {
            return;
        }

        $stock = Stock::query()
            ->where('part_id', $partId)
            ->where('branch_id', $branchId)
            ->first();

        if (! $stock || $stock->quantity >= $part->min_stock) {
            return;
        }

        try {
            Redis::publish(config('frostparts.low_stock_channel', 'low-stock'), json_encode([
                'part_id' => $partId,
                'branch_id' => $branchId,
                'quantity' => $stock->quantity,
                'min_stock' => $part->min_stock,
                'part_code' => $part->code,
                'part_name' => $part->name,
            ]));
        } catch (\Throwable) {
            // Redis optional in dev/tests
        }
    }
}
