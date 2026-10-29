<?php

namespace App\Actions\Auth;

use App\Dto\Auth\CheckTokenDto;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenAction
{
    /**
     * Validate the provided token and return the associated user model.
     *
     * @throws UnauthorizedException
     */
    public function handle(CheckTokenDto $dto): User
    {
        $user = $dto->user;

        if (! $user && $dto->token) {
            $tokenable = PersonalAccessToken::findToken($dto->token)?->tokenable;
            if ($tokenable instanceof User) {
                $user = $tokenable;
            }
        }

        if (! $user) {
            throw new UnauthorizedException('Token inválido ou expirado.');
        }

        return $user;
    }
}
