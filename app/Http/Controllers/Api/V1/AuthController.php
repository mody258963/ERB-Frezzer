<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'user' => (new UserResource($result['user']))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return (new MessageResource(['message' => 'Logged out.']))->response();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('branch'));
    }
}
