<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\Api\V1\ApiFormRequest;

class PartsSalesChartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'branch_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rank_by' => ['nullable', 'in:units,revenue'],
        ];
    }
}
