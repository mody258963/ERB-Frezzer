<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
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

        return response()->json([
            'token' => $tokenResult->accessToken,
            'token_type' => $tokenResult->tokenType ?? 'Bearer',
            'expires_in' => $tokenResult->expiresIn ?? null,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->revoke();

        return (new MessageResource(['message' => 'Logged out.']))->response();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('branch'));
    }
}
