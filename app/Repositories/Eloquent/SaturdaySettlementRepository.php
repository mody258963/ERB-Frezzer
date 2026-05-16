<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaturdaySettlement;
use App\Models\User;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SaturdaySettlementRepository implements SaturdaySettlementRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $query = SaturdaySettlement::query()->with(['customer', 'creator']);

        return $query->latest('created_at')->paginate($perPage);
    }

    public function findWithInvoices(string $id): ?SaturdaySettlement
    {
        return SaturdaySettlement::query()
            ->with(['customer', 'invoices', 'creator'])
            ->find($id);
    }

    public function create(array $data): SaturdaySettlement
    {
        return SaturdaySettlement::query()->create($data);
    }

    public function upcomingTotals(): Collection
    {
        return Customer::query()
            ->where('type', 'credit')
            ->where('is_active', true)
            ->get()
            ->map(function (Customer $c) {
                $due = Invoice::query()
                    ->where('customer_id', $c->id)
                    ->where('payment_type', 'credit')
                    ->where('is_paid', false)
                    ->sum('total');

                return (object) [
                    'customer_id' => $c->id,
                    'customer_name' => $c->name,
                    'amount_due' => $due,
                ];
            })
            ->values();
    }
}
