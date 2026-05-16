<?php

namespace App\Repositories\Contracts;

use App\Models\Stock;
use Illuminate\Support\Collection;

interface StockRepositoryInterface
{
    /**
     * Lock row for update inside an open transaction.
     */
    public function lockForPartAndBranch(string $partId, string $branchId): ?Stock;

    public function firstOrCreate(string $partId, string $branchId): Stock;

    public function adjustQuantity(Stock $stock, int $delta): void;

    /**
     * @return Collection<int, object{part_id: string, branch_id: string, quantity: int, min_stock: int, name: string}>
     */
    public function lowStockByBranch(): Collection;
}
