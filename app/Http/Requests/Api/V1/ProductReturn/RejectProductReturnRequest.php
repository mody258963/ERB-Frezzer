<?php

namespace App\Http\Requests\Api\V1\ProductReturn;

use App\Http\Requests\Api\V1\ApiFormRequest;

class RejectProductReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
