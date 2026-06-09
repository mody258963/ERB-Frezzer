<?php

namespace App\Repositories\Eloquent;

use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierInstallmentRepository implements SupplierInstallmentRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return SupplierInstallment::query()
            ->with(['supplier', 'purchaseOrder'])
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(array_key_exists('is_paid', $filters ?? []) && $filters['is_paid'] !== null, fn ($q) => $q->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')
            ->paginate($perPage);
    }

    public function find(string $id): ?SupplierInstallment
    {
        return SupplierInstallment::query()
            ->with(['supplier', 'purchaseOrder', 'paidByUser'])
            ->find($id);
    }

    public function findOrFail(string $id): SupplierInstallment
    {
        return SupplierInstallment::query()->findOrFail($id);
    }

    public function overdue(): Collection
    {
        return SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('due_date')
            ->get();
    }

    public function save(SupplierInstallment $installment): void
    {
        $installment->save();
    }
}
