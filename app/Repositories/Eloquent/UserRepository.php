<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function defaultRelations(): array
    {
        return ['branch'];
    }

    /**
     * @param  array{branch_id?: string, role?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $activeBranchId = BranchVisibility::activeBranchId();

        return $this->newQuery()
            ->with('branch')
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?User
    {
        return $this->findById($id);
    }

    public function create(array $data): User
    {
        /** @var User */
        return $this->createRecord($data);
    }

    public function update(User $user, array $data): User
    {
        /** @var User */
        return $this->updateRecord($user, $data);
    }
}
