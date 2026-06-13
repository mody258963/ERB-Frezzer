<?php

namespace App\Http\Requests\Api\V1\Inventory;

use App\Enums\PartUnit;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Models\Part;

class AdjustStockRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'part_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $partId = $this->input('part_id');
            $quantityDelta = $this->input('quantity_delta');

            if (! $partId || $quantityDelta === null) {
                return;
            }

            $part = Part::query()->find($partId);
            if ($part === null) {
                return;
            }

            $unit = $part->unit;
            if ($unit instanceof PartUnit && $unit->allowsFractionalQuantity()) {
                return;
            }

            if ((float) $quantityDelta != (int) $quantityDelta) {
                $validator->errors()->add('quantity_delta', 'Quantity must be a whole number for this unit.');
            }
        });
    }
}
