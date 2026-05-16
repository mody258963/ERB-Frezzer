<?php

namespace App\Models;

use App\Enums\PartCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'category', 'unit', 'sell_price', 'cost_price', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'category' => PartCategory::class,
        ];
    }
}
