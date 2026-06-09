<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Customer::query()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?Customer
    {
        return Customer::query()->find($id);
    }

    public function findOrFail(string $id): Customer
    {
        $customer = $this->find($id);

        if ($customer === null) {
            abort(404);
        }

        return $customer;
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }

    public function paginatedInvoices(string $customerId, int $perPage = 50): LengthAwarePaginator
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->with(['branch', 'items.part'])
            ->latest()
            ->paginate($perPage);
    }

    public function unpaidCreditInvoices(string $customerId): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->where('payment_type', 'credit')
            ->where('is_paid', false)
            ->with(['branch', 'items.part'])
            ->get();
    }
}
