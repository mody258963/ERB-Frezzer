<?php

namespace App\Repositories\Contracts;

use App\Models\StockMovement;

interface StockMovementRepositoryInterface
{
    public function create(array $data): StockMovement;
}
