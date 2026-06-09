<?php

namespace App\Http\Requests\Api\V1\ProductReturn;

use App\Http\Requests\Api\V1\ApiFormRequest;

class ApproveProductReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'in:restock,writeoff,replace,refund_cash,credit_note,supplier_credit'],
        ];
    }
}
