<?php

namespace App\Http\Requests\Api\V1\Capital;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Rules\MaxWithdrawableProfit;
use App\Support\BranchVisibility;

class OwnerCashOutRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $branchId = $this->input('branch_id') ?? BranchVisibility::activeBranchId($this->user());

        return [
            'amount' => ['required', 'numeric', 'min:0.01', new MaxWithdrawableProfit($branchId)],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
