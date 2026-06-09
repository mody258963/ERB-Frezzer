<?php

namespace App\Http\Requests\Api\V1\BranchFinance;

use App\Http\Requests\Api\V1\ApiFormRequest;

class IndexBranchFinanceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->perPageRules(), [
            'creditor_branch_id' => ['nullable', 'uuid'],
            'debtor_branch_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:open,settled'],
            'entry_type' => ['nullable', 'in:charge,payment'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'creditor_branch_id',
            'debtor_branch_id',
            'status',
            'entry_type',
        ]);
    }
}
