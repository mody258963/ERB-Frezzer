<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'address', 'phone', 'is_active', 'capital_amount'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capital_amount' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
