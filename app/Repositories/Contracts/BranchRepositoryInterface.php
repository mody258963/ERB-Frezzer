<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface BranchRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator;

    public function allActive(?User $user): Collection;

    public function find(string $id): ?Branch;

    public function create(array $data): Branch;

    public function update(Branch $branch, array $data): Branch;
}
