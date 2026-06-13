<?php

namespace App\Repositories\Eloquent;

use App\Models\Stock;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockRepository implements StockRepositoryInterface
{
    public function lockForPartAndBranch(string $partId, string $branchId): ?Stock
    {
        return Stock::query()
            ->where('part_id', $partId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();
    }

    public function firstOrCreate(string $partId, string $branchId): Stock
    {
        return Stock::query()->firstOrCreate(
            ['part_id' => $partId, 'branch_id' => $branchId],
            ['quantity' => 0]
        );
    }

    public function adjustQuantity(Stock $stock, float|int|string $delta): void
    {
        $stock->increment('quantity', $delta);
    }

    public function lowStockByBranch(): Collection
    {
        return DB::table('stock')
            ->join('parts', 'parts.id', '=', 'stock.part_id')
            ->whereColumn('stock.quantity', '<', 'parts.min_stock')
            ->select([
                'stock.part_id',
                'stock.branch_id',
                'stock.quantity',
                'parts.min_stock',
                'parts.name',
            ])
            ->orderBy('stock.branch_id')
            ->get();
    }
}
