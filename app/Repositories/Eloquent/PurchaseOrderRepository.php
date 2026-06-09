<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    protected function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['supplier', 'branch']);

        BranchVisibility::scope($user, $query, 'branch_id');

        return $query
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithRelations(string $id): ?PurchaseOrder
    {
        return $this->findByIdWith($id, ['items.part', 'installments', 'supplier', 'branch', 'creator']);
    }

    public function findOrFail(string $id): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return $this->findByIdOrFail($id);
    }

    public function nextPoNumber(): string
    {
        return $this->nextSequentialNumber('po_number', 'PO-', 3);
    }

    public function create(array $po, array $items): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return $this->createWithItems($po, $items);
    }

    public function save(PurchaseOrder $po): void
    {
        $this->saveRecord($po);
    }
}
