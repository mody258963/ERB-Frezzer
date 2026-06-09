<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;

class StockMovementRepository extends BaseRepository implements StockMovementRepositoryInterface
{
    protected function modelClass(): string
    {
        return StockMovement::class;
    }

    public function create(array $data): StockMovement
    {
        /** @var StockMovement */
        return $this->createRecord($data);
    }
}
