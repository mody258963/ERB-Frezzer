<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

class IndexCustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->perPageRules(max: 500), [
            'type' => ['nullable', 'in:credit,cash'],
            'search' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array{type: ?string, search: ?string}
     */
    public function filters(): array
    {
        return [
            'type' => $this->validated('type'),
            'search' => $this->validated('search'),
        ];
    }
}
