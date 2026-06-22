<?php

namespace App\Http\Requests\Api\V1\Supplier;

use App\Http\Requests\Api\V1\ApiFormRequest;

class CollectSupplierPaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:cash,bank_transfer,check'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
