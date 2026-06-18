<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchFinancialPaymentAllocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_entry_id',
        'charge_entry_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function paymentEntry(): BelongsTo
    {
        return $this->belongsTo(BranchFinancialEntry::class, 'payment_entry_id');
    }

    public function chargeEntry(): BelongsTo
    {
        return $this->belongsTo(BranchFinancialEntry::class, 'charge_entry_id');
    }
}
