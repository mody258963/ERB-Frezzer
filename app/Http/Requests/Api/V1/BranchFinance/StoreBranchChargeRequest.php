<?php

namespace App\Http\Requests\Api\V1\BranchFinance;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreBranchChargeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'creditor_branch_id' => ['required', 'uuid', 'different:debtor_branch_id'],
            'debtor_branch_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
