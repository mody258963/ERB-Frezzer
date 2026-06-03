<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'capital_amount',
        'currency',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'capital_amount' => 'decimal:2',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
