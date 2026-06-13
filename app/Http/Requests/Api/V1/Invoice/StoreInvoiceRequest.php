<?php

namespace App\Http\Requests\Api\V1\Invoice;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Http\Requests\Api\V1\Concerns\ValidatesItemQuantities;

class StoreInvoiceRequest extends ApiFormRequest
{
    use ValidatesItemQuantities;

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'payment_type' => ['required', 'in:credit,cash'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateItemQuantities($validator);
    }
}
