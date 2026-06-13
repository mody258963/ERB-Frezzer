<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateCustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:credit,cash'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['sometimes', 'numeric'],
            'linked_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'settlement_cycle' => ['nullable', 'in:daily,weekly'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'cash') {
            $this->merge(['settlement_cycle' => null]);
        }
    }
}
