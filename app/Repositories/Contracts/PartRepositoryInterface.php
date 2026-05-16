<?php

namespace App\Repositories\Contracts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PartRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?Part;

    public function create(array $data): Part;

    public function update(Part $part, array $data): Part;
}
