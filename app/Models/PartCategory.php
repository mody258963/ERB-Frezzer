<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    use HasUuids;

    protected $fillable = ['key', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'category_id');
    }
}
