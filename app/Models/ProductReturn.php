<?php

namespace App\Models;

use App\Enums\ReturnReferenceType;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 */
class ProductReturn extends Model
{
    use HasUuids;

    protected $table = 'returns';

    protected $fillable = [
        'return_number', 'return_type', 'reference_id', 'reference_type', 'customer_id', 'supplier_id',
        'branch_id', 'reason', 'status', 'resolution', 'total_value', 'notes', 'attachment_url',
        'approved_by', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'return_type' => ReturnType::class,
            'reference_type' => ReturnReferenceType::class,
            'status' => ReturnStatus::class,
            'resolution' => ReturnResolution::class,
            'total_value' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
