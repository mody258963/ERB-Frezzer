<?php

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository implements SupplierRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        return Supplier::query()->latest()->paginate($perPage);
    }

    public function find(string $id): ?Supplier
    {
        return Supplier::query()->find($id);
    }

    public function create(array $data): Supplier
    {
        return Supplier::query()->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }
}
