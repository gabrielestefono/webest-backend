<?php

namespace App\Actions\Auth;

use App\Dto\Auth\CheckTokenDto;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenAction
{
    /**
     * Validate the provided token and return the associated user model or null.
     */
    public function handle(CheckTokenDto $dto): ?User
    {
        if ($dto->user) {
            return $dto->user;
        }

        if ($dto->token) {
            /** @var User|null $user */
            $user = PersonalAccessToken::findToken($dto->token)?->tokenable;

            return $user;
        }

        return null;
    }
}
