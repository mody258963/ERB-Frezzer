<?php

namespace App\Http\Requests\Api\V1\Branch;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateBranchRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ];
    }
}
