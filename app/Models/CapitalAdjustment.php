<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalAdjustment extends Model
{
    use HasUuids;

    protected $fillable = [
        'previous_amount',
        'new_amount',
        'change_amount',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
