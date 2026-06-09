<?php

namespace App\Repositories\Contracts;

use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductReturnRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findWithItems(string $id): ?ProductReturn;

    public function findOrFail(string $id): ProductReturn;

    public function nextReturnNumber(): string;

    public function create(array $data, array $items): ProductReturn;

    public function save(ProductReturn $return): void;
}
