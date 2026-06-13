<?php

namespace App\Http\Requests\Api\V1\Purchase;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Http\Requests\Api\V1\Concerns\ValidatesItemQuantities;
use App\Models\Supplier;

class StorePurchaseRequest extends ApiFormRequest
{
    use ValidatesItemQuantities;

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string'],
            'payment_type' => ['required', 'in:immediate,installments'],
            'installment_count' => ['nullable', 'integer', 'min:1'],
            'installment_start_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $supplierId = $this->input('supplier_id');
            $branchId = $this->input('branch_id');

            if (! $supplierId || ! $branchId) {
                return;
            }

            $supplier = Supplier::query()->find($supplierId);

            if ($supplier !== null && $supplier->branch_id !== null && $supplier->branch_id !== $branchId) {
                $validator->errors()->add(
                    'supplier_id',
                    'The supplier does not belong to the selected branch.',
                );
            }
        });

        $this->validateItemQuantities($validator);
    }
}
