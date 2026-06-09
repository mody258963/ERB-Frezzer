<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\Api\V1\ApiFormRequest;

class FinancialReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
        ];
    }
}
