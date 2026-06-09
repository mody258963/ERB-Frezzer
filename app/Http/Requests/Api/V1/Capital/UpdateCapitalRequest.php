<?php

namespace App\Http\Requests\Api\V1\Capital;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateCapitalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'capital_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
