<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{user: User, token: string, token_type: string, expires_in: int|null}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account is disabled.'],
            ]);
        }

        $tokenResult = $user->createToken('flutter');

        return [
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'token_type' => $tokenResult->tokenType ?? 'Bearer',
            'expires_in' => $tokenResult->expiresIn ?? null,
        ];
    }

    public function logout(?User $user): void
    {
        $user?->currentAccessToken()?->revoke();
    }
}
