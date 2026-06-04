<?php

namespace App\Models;

use App\Enums\SettlementPaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInstallmentPayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'installment_id',
        'supplier_id',
        'po_id',
        'amount',
        'payment_method',
        'paid_by',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => SettlementPaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(SupplierInstallment::class, 'installment_id');
    }
}
