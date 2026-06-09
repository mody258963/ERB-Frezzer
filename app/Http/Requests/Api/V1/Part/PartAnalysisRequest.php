<?php

namespace App\Http\Requests\Api\V1\Part;

use App\Http\Requests\Api\V1\ApiFormRequest;

class PartAnalysisRequest extends ApiFormRequest
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
