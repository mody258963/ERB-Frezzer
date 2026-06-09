<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository implements SupplierRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        return Supplier::query()->latest()->paginate($perPage);
    }

    public function find(string $id): ?Supplier
    {
        return Supplier::query()->find($id);
    }

    public function create(array $data): Supplier
    {
        return Supplier::query()->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function debtSnapshot(string $supplierId): array
    {
        $supplier = $this->find($supplierId) ?? abort(404);

        return [
            'supplier' => $supplier,
            'purchase_orders' => PurchaseOrder::query()
                ->where('supplier_id', $supplierId)
                ->with(['items.part', 'installments', 'branch', 'supplier', 'creator'])
                ->get(),
            'installments' => SupplierInstallment::query()
                ->where('supplier_id', $supplierId)
                ->orderBy('due_date')
                ->get(),
        ];
    }
}
