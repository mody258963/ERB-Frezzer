<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    /**
     * @param  array{branch_id?: string, role?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->with('branch')
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?User
    {
        return User::query()->with('branch')->find($id);
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh(['branch']);
    }
}
