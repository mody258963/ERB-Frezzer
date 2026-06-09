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

    /**
     * @return array{supplier: Supplier, purchase_orders: \Illuminate\Database\Eloquent\Collection, installments: \Illuminate\Database\Eloquent\Collection}
     */
    public function debtSnapshot(string $supplierId): array;
}
