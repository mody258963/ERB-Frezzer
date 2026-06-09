<?php

namespace App\Http\Requests\Api\V1\Purchase;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StorePurchaseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string'],
            'payment_type' => ['required', 'in:immediate,installments'],
            'installment_count' => ['nullable', 'integer', 'min:1'],
            'installment_start_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
