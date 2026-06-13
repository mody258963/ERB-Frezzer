<?php

namespace App\Repositories\Eloquent;

use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierInstallmentRepository extends BaseRepository implements SupplierInstallmentRepositoryInterface
{
    protected function modelClass(): string
    {
        return SupplierInstallment::class;
    }

    protected function defaultRelations(): array
    {
        return ['supplier', 'purchaseOrder', 'paidByUser'];
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $branchId = BranchVisibility::activeBranchId($user);

        return $this->newQuery()
            ->with(['supplier', 'purchaseOrder'])
            ->when($branchId, fn ($q) => $q->whereHas(
                'purchaseOrder',
                fn ($po) => $po->where('branch_id', $branchId),
            ))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(array_key_exists('is_paid', $filters ?? []) && $filters['is_paid'] !== null, fn ($q) => $q->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')
            ->paginate($perPage);
    }

    public function find(string $id): ?SupplierInstallment
    {
        return $this->findById($id);
    }

    public function findOrFail(string $id): SupplierInstallment
    {
        /** @var SupplierInstallment */
        return $this->findByIdOrFail($id);
    }

    public function overdue(): Collection
    {
        $branchId = BranchVisibility::activeBranchId();

        return $this->newQuery()
            ->where('is_paid', false)
            ->whereDate('due_date', '<', now()->toDateString())
            ->when($branchId, fn ($q) => $q->whereHas(
                'purchaseOrder',
                fn ($po) => $po->where('branch_id', $branchId),
            ))
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('due_date')
            ->get();
    }

    public function save(SupplierInstallment $installment): void
    {
        $this->saveRecord($installment);
    }
}
