<?php

namespace App\Transformers;

use App\Models\Customer;
use App\Transformers\Concerns\TransformsBackedEnums;

final class CustomerTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'type' => self::enumValue($customer->type),
            'phone' => $customer->phone,
            'address' => $customer->address,
            'credit_limit' => (float) $customer->credit_limit,
            'outstanding_balance' => (float) $customer->outstanding_balance,
            'last_settled_at' => $customer->last_settled_at?->toISOString(),
            'is_active' => $customer->is_active,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
