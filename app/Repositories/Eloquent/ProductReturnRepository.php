<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductReturn;
use App\Models\User;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductReturnRepository extends BaseRepository implements ProductReturnRepositoryInterface
{
    protected function modelClass(): string
    {
        return ProductReturn::class;
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['customer', 'supplier', 'branch']);

        BranchVisibility::scope($user, $query, 'branch_id');

        return $query
            ->when($filters['return_type'] ?? null, fn ($q, $t) => $q->where('return_type', $t))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithItems(string $id): ?ProductReturn
    {
        return $this->findByIdWith($id, ['items.part', 'customer', 'supplier', 'branch', 'creator', 'approver']);
    }

    public function findOrFail(string $id): ProductReturn
    {
        /** @var ProductReturn */
        return $this->findByIdOrFail($id);
    }

    public function nextReturnNumber(): string
    {
        return $this->nextSequentialNumber('return_number', 'RET-', 3);
    }

    public function create(array $data, array $items): ProductReturn
    {
        /** @var ProductReturn */
        return $this->createWithItems($data, $items);
    }

    public function save(ProductReturn $return): void
    {
        $this->saveRecord($return);
    }
}
