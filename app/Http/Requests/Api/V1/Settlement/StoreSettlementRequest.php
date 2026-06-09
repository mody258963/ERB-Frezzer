<?php

namespace App\Http\Requests\Api\V1\Settlement;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreSettlementRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'settlement_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,check'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
