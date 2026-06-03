<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * @param  array{branch_id?: string, role?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;
}
