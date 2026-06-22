<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function modelClass(): string
    {
        return Supplier::class;
    }

    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $branchId = BranchVisibility::activeBranchId($user);

        return $this->newQuery()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?Supplier
    {
        return $this->findById($id);
    }

    public function create(array $data, ?User $user = null): Supplier
    {
        $user = $user ?? request()?->user();
        unset($data['branch_id']);

        $branchId = BranchVisibility::activeBranchId($user);
        if ($branchId === null) {
            throw new \InvalidArgumentException(
                'branch_id is required to create a supplier. Send ?branch_id= on POST, include branch_id in JSON, or use the X-Branch-Id header.',
            );
        }

        $data['branch_id'] = $branchId;

        /** @var Supplier */
        return $this->createRecord($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        /** @var Supplier */
        return $this->updateRecord($supplier, $data);
    }

    public function debtSnapshot(string $supplierId, ?string $branchId = null): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->findByIdOrFail($supplierId);
        $branchId = $branchId ?? BranchVisibility::activeBranchId();

        $poQuery = PurchaseOrder::query()->where('supplier_id', $supplierId);
        $installmentQuery = SupplierInstallment::query()->where('supplier_id', $supplierId);

        if ($branchId !== null) {
            $poQuery->where('branch_id', $branchId);
            $installmentQuery->whereHas('purchaseOrder', fn ($po) => $po->where('branch_id', $branchId));
        }

        return [
            'supplier' => $supplier,
            'purchase_orders' => $poQuery
                ->with(['items.part', 'installments', 'branch', 'supplier', 'creator'])
                ->get(),
            'installments' => $installmentQuery->orderBy('due_date')->get(),
        ];
    }

    public function debtsWithBalance(?string $branchId = null): array
    {
        $branchId = $branchId ?? BranchVisibility::activeBranchId();

        $supplierQuery = Supplier::query()
            ->where('is_active', true)
            ->where('total_debt', '>', 0)
            ->orderBy('name');

        if ($branchId !== null) {
            $supplierQuery->where('branch_id', $branchId);
        }

        return $supplierQuery
            ->get()
            ->map(fn (Supplier $supplier) => $this->debtSnapshot($supplier->id, $branchId))
            ->all();
    }
}
