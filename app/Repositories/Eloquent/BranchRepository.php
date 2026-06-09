<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected function modelClass(): string
    {
        return Branch::class;
    }

    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        return $this->newQuery()->latest()->paginate($perPage);
    }

    public function allActive(?User $user): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->when($user?->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): ?Branch
    {
        return $this->findById($id);
    }

    public function create(array $data): Branch
    {
        /** @var Branch */
        return $this->createRecord($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        /** @var Branch */
        return $this->updateRecord($branch, $data);
    }
}
