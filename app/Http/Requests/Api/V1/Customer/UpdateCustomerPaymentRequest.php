<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateCustomerPaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => ['sometimes', 'in:cash,bank_transfer,check'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
