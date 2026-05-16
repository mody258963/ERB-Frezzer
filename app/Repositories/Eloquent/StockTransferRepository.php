<?php

namespace App\Repositories\Eloquent;

use App\Models\StockTransfer;
use App\Models\User;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockTransferRepository implements StockTransferRepositoryInterface
{
    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $query = StockTransfer::query()->with(['fromBranch', 'toBranch', 'creator']);

        if ($user?->branch_id) {
            $query->where(function ($q) use ($user) {
                $q->where('from_branch_id', $user->branch_id)
                    ->orWhere('to_branch_id', $user->branch_id);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function findWithItems(string $id): ?StockTransfer
    {
        return StockTransfer::query()
            ->with(['items.part', 'fromBranch', 'toBranch', 'creator'])
            ->find($id);
    }

    public function create(array $data, array $items): StockTransfer
    {
        $t = StockTransfer::query()->create($data);
        foreach ($items as $item) {
            $t->items()->create($item);
        }

        return $t->load('items');
    }

    public function save(StockTransfer $transfer): void
    {
        $transfer->save();
    }
}
