<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Enums\SettlementCycle;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'type', 'phone', 'address', 'credit_limit', 'outstanding_balance',
        'last_settled_at', 'linked_supplier_id', 'is_active', 'branch_id', 'settlement_cycle',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'settlement_cycle' => SettlementCycle::class,
            'credit_limit' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'last_settled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function linkedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'linked_supplier_id');
    }

    public function contraSettlements(): HasMany
    {
        return $this->hasMany(ContraSettlement::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
