<?php

namespace App\Actions\Auth;

use App\Dto\Auth\LogoutDto;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutAction
{
    /**
     * Revoke current user's token.
     *
     * @return array<string, bool>
     */
    public function handle(LogoutDto $dto): array
    {
        if ($dto->user) {
            $dto->user->currentAccessToken()?->delete();
        } elseif ($dto->token) {
            PersonalAccessToken::findToken($dto->token)?->delete();
        }

        return [
            'success' => true,
        ];
    }
}
