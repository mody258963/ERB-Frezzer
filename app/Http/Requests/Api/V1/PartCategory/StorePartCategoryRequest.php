<?php

namespace App\Http\Requests\Api\V1\PartCategory;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StorePartCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:part_categories,key'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
