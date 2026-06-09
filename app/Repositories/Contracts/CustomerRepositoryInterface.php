<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CustomerRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?Customer;

    public function findOrFail(string $id): Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function paginatedInvoices(string $customerId, int $perPage = 50): LengthAwarePaginator;

    public function unpaidCreditInvoices(string $customerId): Collection;
}
