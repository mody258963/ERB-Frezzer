<?php

namespace App\Http\Requests\Api\V1\Part;

use App\Enums\PartUnit;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Http\Requests\Api\V1\Part\Concerns\ResolvesPartBranch;
use Illuminate\Validation\Rule;

class StorePartRequest extends ApiFormRequest
{
    use ResolvesPartBranch;

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('parts', 'code')->where(function ($query) {
                    $branchId = $this->resolvedPartBranchId();

                    return $branchId === null
                        ? $query->whereNull('branch_id')
                        : $query->where('branch_id', $branchId);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid', 'exists:part_categories,id', 'required_without:category_key'],
            'category_key' => ['nullable', 'string', 'max:64', 'required_without:category_id'],
            'unit' => ['required', Rule::enum(PartUnit::class)],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'initial_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
