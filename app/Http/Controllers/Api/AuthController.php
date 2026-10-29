<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\CheckTokenAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RegisterAction;
use App\Actions\Auth\SendConfirmationEmailAction;
use App\Actions\Auth\VerifyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckTokenRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendConfirmationEmailRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\Auth\CheckTokenResource;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Resources\Auth\LogoutResource;
use App\Http\Resources\Auth\RegisterResource;
use App\Http\Resources\Auth\SendConfirmationEmailResource;
use App\Http\Resources\Auth\VerifyEmailResource;

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
     * Register a new user and issue a Sanctum token.
     */
    public function register(RegisterRequest $request, RegisterAction $action): RegisterResource
    {
        return new RegisterResource($action->handle($request->toDto()));
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

    /**
     * Send email verification notification to the authenticated user.
     */
    public function sendConfirmationEmail(SendConfirmationEmailRequest $request, SendConfirmationEmailAction $action): SendConfirmationEmailResource
    {
        return new SendConfirmationEmailResource($action->handle($request->toDto()));
    }

    /**
     * Verify the user's email address using signed query params.
     */
    public function verifyEmail(VerifyEmailRequest $request, VerifyEmailAction $action): VerifyEmailResource
    {
        return new VerifyEmailResource($action->handle($request->toDto()));
    }
}
