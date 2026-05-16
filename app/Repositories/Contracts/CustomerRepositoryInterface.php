<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;
}
