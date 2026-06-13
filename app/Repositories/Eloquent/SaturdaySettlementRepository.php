<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\SaturdaySettlement;
use App\Models\User;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use App\Support\BranchVisibility;
use App\Support\CustomerSettlementSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SaturdaySettlementRepository extends BaseRepository implements SaturdaySettlementRepositoryInterface
{
    protected function modelClass(): string
    {
        return SaturdaySettlement::class;
    }

    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $branchId = BranchVisibility::activeBranchId($user);

        return $this->newQuery()
            ->with(['customer', 'creator'])
            ->when($branchId, fn ($q) => $q->whereHas(
                'customer',
                fn ($c) => $c->where('branch_id', $branchId),
            ))
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function findWithInvoices(string $id): ?SaturdaySettlement
    {
        return $this->findByIdWith($id, ['customer', 'invoices', 'creator']);
    }

    public function create(array $data): SaturdaySettlement
    {
        /** @var SaturdaySettlement */
        return $this->createRecord($data);
    }

    public function upcomingTotals(?string $cycle = null): Collection
    {
        $branchId = BranchVisibility::activeBranchId();

        return Customer::query()
            ->where('type', 'credit')
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($cycle, fn ($q) => $q->where('settlement_cycle', $cycle))
            ->get()
            ->map(function (Customer $customer) {
                $due = (float) $customer->outstanding_balance;

                return (object) [
                    'customer' => $customer,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'amount_due' => $due,
                    'settlement_cycle' => $customer->settlement_cycle?->value ?? 'weekly',
                ];
            })
            ->filter(fn (object $row) => CustomerSettlementSchedule::isDue($row->customer, $row->amount_due))
            ->map(fn (object $row) => (object) [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name,
                'amount_due' => $row->amount_due,
                'settlement_cycle' => $row->settlement_cycle,
            ])
            ->values();
    }
}
