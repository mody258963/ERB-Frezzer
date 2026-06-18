<?php

namespace App\Http\Requests\Api\V1\BranchFinance;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateBranchFinancialEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
