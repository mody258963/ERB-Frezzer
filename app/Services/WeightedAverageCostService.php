<?php

namespace App\Services;

use App\Models\Part;
use App\Models\Stock;

class WeightedAverageCostService
{
    public function blendInbound(Stock $stock, float|int|string $incomingQty, string $incomingUnitCost): string
    {
        $incomingQty = (string) $incomingQty;

        if (bccomp($incomingQty, '0', 4) <= 0) {
            return (string) $stock->average_cost;
        }

        $oldQty = (string) $stock->quantity;
        $oldAvg = (string) $stock->average_cost;

        if (bccomp($oldQty, '0', 4) <= 0) {
            return $incomingUnitCost;
        }

        $oldValue = bcmul($oldQty, $oldAvg, 4);
        $inValue = bcmul($incomingQty, $incomingUnitCost, 4);
        $newQty = bcadd($oldQty, $incomingQty, 4);

        return bcdiv(bcadd($oldValue, $inValue, 4), $newQty, 2);
    }

    public function applyInbound(Stock $stock, float|int|string $incomingQty, string $incomingUnitCost): void
    {
        $incomingQty = (string) $incomingQty;

        if (bccomp($incomingQty, '0', 4) <= 0) {
            return;
        }

        $stock->average_cost = $this->blendInbound($stock, $incomingQty, $incomingUnitCost);
        $stock->quantity = bcadd((string) $stock->quantity, $incomingQty, 4);
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

        $totalQty = '0';
        $totalValue = '0';

        foreach ($rows as $row) {
            $qty = (string) $row->quantity;
            if (bccomp($qty, '0', 4) <= 0) {
                continue;
            }

            $totalQty = bcadd($totalQty, $qty, 4);
            $totalValue = bcadd($totalValue, bcmul($qty, (string) $row->average_cost, 4), 4);
        }

        $rollup = bccomp($totalQty, '0', 4) > 0
            ? bcdiv($totalValue, $totalQty, 2)
            : '0';

        Part::query()->whereKey($partId)->update(['cost_price' => $rollup]);
    }
}
