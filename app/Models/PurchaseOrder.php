<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchasePaymentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'po_number', 'supplier_id', 'branch_id', 'description', 'total_amount', 'amount_paid',
        'payment_type', 'status', 'received_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'payment_type' => PurchasePaymentType::class,
            'status' => PurchaseOrderStatus::class,
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SupplierInstallment::class, 'po_id');
    }
}
