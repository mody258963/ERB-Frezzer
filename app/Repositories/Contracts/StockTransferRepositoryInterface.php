<?php

namespace App\Repositories\Contracts;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StockTransferRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator;

    public function findWithItems(string $id): ?StockTransfer;

    public function findOrFail(string $id): StockTransfer;

    public function create(array $data, array $items): StockTransfer;

    public function save(StockTransfer $transfer): void;

    /**
     * @param  list<array{part_id: string, quantity: float|int|string, unit_cost?: float|int|string|null}>  $items
     */
    public function syncItems(StockTransfer $transfer, array $items): StockTransfer;
}
