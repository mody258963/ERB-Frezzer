<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductReturn;
use App\Models\User;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductReturnRepository implements ProductReturnRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = ProductReturn::query()->with(['customer', 'supplier', 'branch']);

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
        return ProductReturn::query()
            ->with(['items.part', 'customer', 'supplier', 'branch', 'creator', 'approver'])
            ->find($id);
    }

    public function findOrFail(string $id): ProductReturn
    {
        return ProductReturn::query()->findOrFail($id);
    }

    public function nextReturnNumber(): string
    {
        $max = ProductReturn::query()->max('return_number');
        $n = 1;
        if ($max && preg_match('/(\d+)$/', (string) $max, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return 'RET-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): ProductReturn
    {
        $ret = ProductReturn::query()->create($data);
        foreach ($items as $item) {
            $ret->items()->create($item);
        }

        return $ret->load('items');
    }

    public function save(ProductReturn $return): void
    {
        $return->save();
    }
}
