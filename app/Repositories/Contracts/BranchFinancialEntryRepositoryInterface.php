<?php

namespace App\Repositories\Contracts;

use App\Models\BranchFinancialEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BranchFinancialEntryRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function find(string $id): ?BranchFinancialEntry;

    public function create(array $data): BranchFinancialEntry;

    public function save(BranchFinancialEntry $entry): void;

    public function nextEntryNumber(): string;

    /**
     * @return Collection<int, BranchFinancialEntry>
     */
    public function openChargesBetween(string $creditorBranchId, string $debtorBranchId): Collection;
}
