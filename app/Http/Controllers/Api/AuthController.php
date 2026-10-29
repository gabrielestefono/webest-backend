<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\CheckTokenAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckTokenRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Resources\Auth\CheckTokenResource;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Resources\Auth\LogoutResource;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue a Sanctum token.
     */
    public function login(LoginRequest $request, LoginAction $action): LoginResource
    {
        return new LoginResource($action->handle($request->toDto()));
    }

    /**
     * Validate the provided token and return user profile.
     */
    public function checkToken(CheckTokenRequest $request, CheckTokenAction $action): CheckTokenResource
    {
        return new CheckTokenResource($action->handle($request->toDto()));
    }

    /**
     * Revoke current user's token.
     */
    public function logout(LogoutRequest $request, LogoutAction $action): LogoutResource
    {
        return new LogoutResource($action->handle($request->toDto()));
    }
}
