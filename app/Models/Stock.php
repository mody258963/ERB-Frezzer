<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasUuids;

    protected $table = 'stock';

    protected $fillable = ['part_id', 'branch_id', 'quantity', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'average_cost' => 'decimal:2',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Stock rows for parts still in the active catalog (not soft-deleted).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForActiveParts($query)
    {
        return $query->whereHas('part', fn ($q) => $q->where('is_active', true));
    }
}
