<?php

namespace App\Repositories\Eloquent;

use App\Models\Part;
use App\Models\User;
use App\Repositories\Contracts\PartRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartRepository extends BaseRepository implements PartRepositoryInterface
{
    protected function modelClass(): string
    {
        return Part::class;
    }

    protected function defaultRelations(): array
    {
        return ['category'];
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $branchId = BranchVisibility::activeBranchId($user);

        return $this->newQuery()
            ->with(['category'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['category'] ?? null, fn ($q, $key) => $q->whereHas(
                'category',
                fn ($c) => $c->where('key', $key)->orWhere('name', $key)
            ))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when(! empty($filters['low_stock']), function ($q) use ($branchId) {
                return $q->whereIn('id', function ($sub) use ($branchId) {
                    $sub->select('part_id')
                        ->from('stock')
                        ->join('parts', 'parts.id', '=', 'stock.part_id')
                        ->whereColumn('stock.quantity', '<', 'parts.min_stock')
                        ->when($branchId, fn ($s) => $s->where('stock.branch_id', $branchId));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?Part
    {
        return $this->findById($id);
    }

    public function create(array $data, ?User $user = null): Part
    {
        $user = $user ?? request()?->user();
        unset($data['branch_id'], $data['initial_quantity']);

        $branchId = BranchVisibility::activeBranchId($user);
        if ($branchId === null) {
            throw new \InvalidArgumentException('branch_id is required to create a part.');
        }

        $data['branch_id'] = $branchId;

        /** @var Part */
        return $this->createRecord($data);
    }

    public function update(Part $part, array $data): Part
    {
        /** @var Part */
        return $this->updateRecord($part, $data);
    }
}
