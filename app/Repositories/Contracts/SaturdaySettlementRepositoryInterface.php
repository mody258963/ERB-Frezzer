<?php

namespace App\Repositories\Contracts;

use App\Models\SaturdaySettlement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SaturdaySettlementRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator;

    public function findWithInvoices(string $id): ?SaturdaySettlement;

    public function create(array $data): SaturdaySettlement;

    public function upcomingTotals(?string $cycle = null): Collection;
}
