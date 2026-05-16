<?php

namespace App\Repositories\Contracts;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?Supplier;

    public function create(array $data): Supplier;

    public function update(Supplier $supplier, array $data): Supplier;
}
