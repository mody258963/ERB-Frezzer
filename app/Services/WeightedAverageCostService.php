<?php

namespace App\Services;

use App\Models\Part;
use App\Models\Stock;

class WeightedAverageCostService
{
    public function blendInbound(Stock $stock, int $incomingQty, string $incomingUnitCost): string
    {
        if ($incomingQty <= 0) {
            return (string) $stock->average_cost;
        }

        $oldQty = (int) $stock->quantity;
        $oldAvg = (string) $stock->average_cost;

        if ($oldQty <= 0) {
            return $incomingUnitCost;
        }

        $oldValue = bcmul((string) $oldQty, $oldAvg, 4);
        $inValue = bcmul((string) $incomingQty, $incomingUnitCost, 4);
        $newQty = $oldQty + $incomingQty;

        return bcdiv(bcadd($oldValue, $inValue, 4), (string) $newQty, 2);
    }

    public function applyInbound(Stock $stock, int $incomingQty, string $incomingUnitCost): void
    {
        if ($incomingQty <= 0) {
            return;
        }

        $stock->average_cost = $this->blendInbound($stock, $incomingQty, $incomingUnitCost);
        $stock->quantity += $incomingQty;
        $stock->save();

        $this->syncPartRollupCost($stock->part_id);
    }

    public function snapshotCost(Stock $stock): string
    {
        $avg = (string) $stock->average_cost;
        if (bccomp($avg, '0', 2) > 0) {
            return $avg;
        }

        $part = $stock->relationLoaded('part') ? $stock->part : Part::query()->find($stock->part_id);

        return (string) ($part?->cost_price ?? '0');
    }

    public function syncPartRollupCost(string $partId): void
    {
        $rows = Stock::query()
            ->where('part_id', $partId)
            ->get(['quantity', 'average_cost']);

        $totalQty = 0;
        $totalValue = '0';

        foreach ($rows as $row) {
            $qty = (int) $row->quantity;
            if ($qty <= 0) {
                continue;
            }

            $totalQty += $qty;
            $totalValue = bcadd($totalValue, bcmul((string) $qty, (string) $row->average_cost, 4), 4);
        }

        $rollup = $totalQty > 0
            ? bcdiv($totalValue, (string) $totalQty, 2)
            : '0';

        Part::query()->whereKey($partId)->update(['cost_price' => $rollup]);
    }
}
