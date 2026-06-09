<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function modelClass(): string
    {
        return Supplier::class;
    }

    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        return $this->newQuery()->latest()->paginate($perPage);
    }

    public function find(string $id): ?Supplier
    {
        return $this->findById($id);
    }

    public function create(array $data): Supplier
    {
        /** @var Supplier */
        return $this->createRecord($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        /** @var Supplier */
        return $this->updateRecord($supplier, $data);
    }

    public function debtSnapshot(string $supplierId): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->findByIdOrFail($supplierId);

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
