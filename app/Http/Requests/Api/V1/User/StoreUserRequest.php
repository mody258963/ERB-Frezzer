<?php

namespace App\Http\Requests\Api\V1\User;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(UserRole::all())],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (in_array($this->input('role'), [UserRole::Salesperson->value, UserRole::Warehouse->value], true)
                && empty($this->input('branch_id'))) {
                $validator->errors()->add('branch_id', 'Branch is required for salesperson and warehouse roles.');
            }
        });
    }
}
