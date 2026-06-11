<?php

namespace App\Http\Requests\Api\V1\Inventory;

use App\Http\Requests\Api\V1\ApiFormRequest;

class AdjustStockRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'part_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
