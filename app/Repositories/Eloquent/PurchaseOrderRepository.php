<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['supplier', 'branch']);

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
        return PurchaseOrder::query()
            ->with(['items.part', 'installments', 'supplier', 'branch', 'creator'])
            ->find($id);
    }

    public function nextPoNumber(): string
    {
        $max = PurchaseOrder::query()->max('po_number');
        $n = 1;
        if ($max && preg_match('/(\d+)$/', (string) $max, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return 'PO-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public function create(array $po, array $items): PurchaseOrder
    {
        $order = PurchaseOrder::query()->create($po);
        foreach ($items as $item) {
            $order->items()->create($item);
        }

        return $order->load('items');
    }

    public function save(PurchaseOrder $po): void
    {
        $po->save();
    }
}
