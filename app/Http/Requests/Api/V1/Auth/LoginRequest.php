<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
