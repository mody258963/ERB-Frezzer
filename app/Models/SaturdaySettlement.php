<?php

namespace App\Models;

use App\Enums\SettlementPaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaturdaySettlement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'settlement_date', 'customer_id', 'total_amount', 'payment_method', 'notes', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'total_amount' => 'decimal:2',
            'payment_method' => SettlementPaymentMethod::class,
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'settlement_id');
    }
}
