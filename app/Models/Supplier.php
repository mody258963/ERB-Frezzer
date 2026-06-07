<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'contact_person', 'phone', 'address', 'total_debt', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_debt' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function linkedCustomer(): HasOne
    {
        return $this->hasOne(Customer::class, 'linked_supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
