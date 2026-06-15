<?php

namespace App\Repositories\Eloquent;

use App\Models\StockTransfer;
use App\Models\User;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockTransferRepository extends BaseRepository implements StockTransferRepositoryInterface
{
    protected function modelClass(): string
    {
        return StockTransfer::class;
    }

    public function paginate(?User $user, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['fromBranch', 'toBranch', 'creator']);

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
        return $this->findByIdWith($id, ['items.part', 'fromBranch', 'toBranch', 'creator']);
    }

    public function findOrFail(string $id): StockTransfer
    {
        /** @var StockTransfer */
        return $this->findByIdOrFail($id);
    }

    public function create(array $data, array $items): StockTransfer
    {
        /** @var StockTransfer */
        return $this->createWithItems($data, $items);
    }

    public function save(StockTransfer $transfer): void
    {
        $this->saveRecord($transfer);
    }

    /**
     * @param  list<array{part_id: string, quantity: float|int|string, unit_cost?: float|int|string|null}>  $items
     */
    public function syncItems(StockTransfer $transfer, array $items): StockTransfer
    {
        $transfer->items()->delete();

        foreach ($items as $item) {
            $transfer->items()->create([
                'part_id' => $item['part_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'] ?? null,
            ]);
        }

        return $transfer->fresh(['items.part', 'fromBranch', 'toBranch', 'creator']);
    }
}
