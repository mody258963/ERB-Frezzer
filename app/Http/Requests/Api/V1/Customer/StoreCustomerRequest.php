<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreCustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'type' => ['required', 'in:credit,cash'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'linked_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'settlement_cycle' => ['nullable', 'in:daily,weekly'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'credit' && ! $this->has('credit_limit')) {
            $this->merge(['credit_limit' => 0]);
        }

        if ($this->input('type') === 'credit' && ! $this->has('settlement_cycle')) {
            $this->merge(['settlement_cycle' => 'weekly']);
        }

        if ($this->input('type') === 'cash') {
            $this->merge([
                'credit_limit' => 0,
                'outstanding_balance' => 0,
                'settlement_cycle' => null,
            ]);
        }

        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
