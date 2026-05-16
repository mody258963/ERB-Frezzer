<?php

namespace App\Models;

use App\Enums\SettlementPaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInstallment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'po_id', 'supplier_id', 'installment_no', 'amount', 'due_date', 'is_paid', 'paid_at',
        'payment_method', 'paid_by', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
            'payment_method' => SettlementPaymentMethod::class,
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
