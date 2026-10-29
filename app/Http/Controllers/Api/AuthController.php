<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\CheckTokenAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckTokenRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue a Sanctum token.
     */
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->handle($request->toDto());

        if (isset($result['error'])) {
            return response()->json($result, 401);
        }

        return response()->json($result);
    }

    /**
     * Validate the provided token and return user profile.
     */
    public function checkToken(CheckTokenRequest $request, CheckTokenAction $action): JsonResponse
    {
        $user = $action->handle($request->toDto());

        if (! $user) {
            return response()->json([
                'error' => 'Token inválido ou expirado.',
            ], 401);
        }

        return response()->json($user);
    }

    /**
     * Revoke current user's token.
     */
    public function logout(LogoutRequest $request, LogoutAction $action): JsonResponse
    {
        $result = $action->handle($request->toDto());

        return response()->json($result);
    }
}
