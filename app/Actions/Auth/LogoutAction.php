<?php

namespace App\Actions\Auth;

use App\Dto\Auth\LogoutDto;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutAction
{
    /**
     * Revoke current user's token.
     */
    public function handle(LogoutDto $dto): void
    {
        if ($dto->user) {
            $dto->user->currentAccessToken()?->delete();
        } elseif ($dto->token) {
            PersonalAccessToken::findToken($dto->token)?->delete();
        }
    }
}
