<?php

namespace App\Repositories\Eloquent;

use App\Models\Part;
use App\Models\User;
use App\Repositories\Contracts\PartRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartRepository implements PartRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Part::query()
            ->with(['category'])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['category'] ?? null, fn ($q, $key) => $q->whereHas(
                'category',
                fn ($c) => $c->where('key', $key)->orWhere('name', $key)
            ))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when(! empty($filters['low_stock']), function ($q) {
                return $q->whereIn('id', function ($sub) {
                    $sub->select('part_id')
                        ->from('stock')
                        ->join('parts', 'parts.id', '=', 'stock.part_id')
                        ->whereColumn('stock.quantity', '<', 'parts.min_stock');
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?Part
    {
        return Part::query()->with(['category'])->find($id);
    }

    public function create(array $data): Part
    {
        return Part::query()->create($data);
    }

    public function update(Part $part, array $data): Part
    {
        $part->update($data);

        return $part->fresh(['category']);
    }
}
