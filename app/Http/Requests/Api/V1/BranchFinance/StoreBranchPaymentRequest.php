<?php

namespace App\Http\Requests\Api\V1\BranchFinance;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreBranchPaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'creditor_branch_id' => ['required', 'uuid', 'different:debtor_branch_id'],
            'debtor_branch_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
