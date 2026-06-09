<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function perPageRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(int $default = 25): int
    {
        return (int) ($this->validated('per_page') ?? $default);
    }
}
