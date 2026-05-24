<?php

namespace App\Models;

use App\Enums\PartUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Part extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'category_id', 'unit', 'sell_price', 'cost_price', 'min_stock', 'is_active', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'unit' => PartUnit::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }
}
