<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Http\Requests\Api\V1\ApiFormRequest;

class BranchScopedRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid'],
        ];
    }
}
