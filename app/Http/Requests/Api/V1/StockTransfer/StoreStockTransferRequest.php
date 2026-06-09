<?php

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreStockTransferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', 'uuid'],
            'to_branch_id' => ['required', 'uuid', 'different:from_branch_id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
