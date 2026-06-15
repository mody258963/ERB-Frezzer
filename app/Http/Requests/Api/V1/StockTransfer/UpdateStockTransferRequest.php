<?php

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Http\Requests\Api\V1\Concerns\ValidatesItemQuantities;

class UpdateStockTransferRequest extends ApiFormRequest
{
    use ValidatesItemQuantities;

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.part_id' => ['required_with:items', 'uuid'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateItemQuantities($validator);
    }
}
