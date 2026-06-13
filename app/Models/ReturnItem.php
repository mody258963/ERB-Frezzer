<?php

namespace App\Models;

use App\Enums\ReturnItemCondition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'return_id', 'part_id', 'quantity', 'unit_price', 'unit_cost', 'condition', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'condition' => ReturnItemCondition::class,
        ];
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
