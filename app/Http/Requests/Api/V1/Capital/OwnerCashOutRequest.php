<?php

namespace App\Http\Requests\Api\V1\Capital;

use App\Http\Requests\Api\V1\ApiFormRequest;

class OwnerCashOutRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
