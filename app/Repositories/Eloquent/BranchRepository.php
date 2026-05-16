<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $query = Branch::query()->latest();

        return $query->paginate($perPage);
    }

    public function allActive(?User $user): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->when($user?->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): ?Branch
    {
        return Branch::query()->find($id);
    }

    public function create(array $data): Branch
    {
        return Branch::query()->create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->fresh();
    }
}
