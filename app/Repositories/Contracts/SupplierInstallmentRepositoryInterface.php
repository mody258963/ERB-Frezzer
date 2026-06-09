<?php

namespace App\Repositories\Contracts;

use App\Models\SupplierInstallment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SupplierInstallmentRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?SupplierInstallment;

    public function findOrFail(string $id): SupplierInstallment;

    public function overdue(): Collection;

    public function save(SupplierInstallment $installment): void;
}
