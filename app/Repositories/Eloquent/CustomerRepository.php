<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected function modelClass(): string
    {
        return Customer::class;
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $branchId = BranchVisibility::activeBranchId($user);

        return $this->newQuery()
            ->when($branchId, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereHas('invoices', fn ($inv) => $inv->where('branch_id', $branchId));
            }))
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
        return $this->findById($id);
    }

    public function findOrFail(string $id): Customer
    {
        /** @var Customer */
        return $this->findByIdOrFail($id);
    }

    public function create(array $data): Customer
    {
        if (($branchId = BranchVisibility::activeBranchId()) !== null) {
            $data['branch_id'] = $branchId;
        }

        /** @var Customer */
        return $this->createRecord($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        /** @var Customer */
        return $this->updateRecord($customer, $data);
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
