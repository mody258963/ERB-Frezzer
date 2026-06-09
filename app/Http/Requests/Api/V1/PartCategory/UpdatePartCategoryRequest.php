<?php

namespace App\Http\Requests\Api\V1\PartCategory;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdatePartCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $id = (string) $this->route('id');

        return [
            'key' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('part_categories', 'key')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
