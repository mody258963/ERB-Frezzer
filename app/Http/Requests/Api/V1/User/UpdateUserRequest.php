<?php

namespace App\Http\Requests\Api\V1\User;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('id')
            ? User::query()->find($this->route('id'))
            : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['sometimes', Rule::in(UserRole::all())],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = User::query()->find($this->route('id'));
            $role = $this->input('role', $user?->role->value);

            if (in_array($role, [UserRole::Salesperson->value, UserRole::Warehouse->value], true)
                && empty($this->input('branch_id', $user?->branch_id))) {
                $validator->errors()->add('branch_id', 'Branch is required for salesperson and warehouse roles.');
            }
        });
    }
}
