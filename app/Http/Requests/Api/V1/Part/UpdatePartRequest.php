<?php

namespace App\Http\Requests\Api\V1\Part;

use App\Enums\PartUnit;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Models\Part;
use Illuminate\Validation\Rule;

class UpdatePartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $id = (string) $this->route('id');
        $branchId = Part::query()->whereKey($id)->value('branch_id');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('parts', 'code')
                    ->ignore($id)
                    ->where(function ($query) use ($branchId) {
                        return $branchId === null
                            ? $query->whereNull('branch_id')
                            : $query->where('branch_id', $branchId);
                    }),
            ],
            'name' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'uuid', 'exists:part_categories,id'],
            'category_key' => ['sometimes', 'string', 'max:64'],
            'unit' => ['sometimes', Rule::enum(PartUnit::class)],
            'sell_price' => ['sometimes', 'numeric'],
            'min_stock' => ['sometimes', 'integer'],
            'is_active' => ['boolean'],
        ];
    }
}
