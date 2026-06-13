<?php

namespace App\Http\Requests\Api\V1\Supplier;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Http\Requests\Api\V1\Supplier\Concerns\ResolvesSupplierBranch;

class StoreSupplierRequest extends ApiFormRequest
{
    use ResolvesSupplierBranch;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (($branchId = $this->resolvedSupplierBranchId()) !== null) {
            $this->merge(['branch_id' => $branchId]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->resolvedSupplierBranchId() === null) {
                $validator->errors()->add(
                    'branch_id',
                    'branch_id is required. Send ?branch_id= on POST, include branch_id in JSON, or use the X-Branch-Id header.',
                );
            }
        });
    }
}
