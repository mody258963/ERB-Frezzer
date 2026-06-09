<?php

namespace App\Http\Requests\Api\V1\Supplier;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateSupplierRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }
}
