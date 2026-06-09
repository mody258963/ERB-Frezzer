<?php

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class CompleteStockTransferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'valuation' => ['nullable', 'in:cost,sell'],
            'record_branch_charge' => ['nullable', 'boolean'],
        ];
    }
}
