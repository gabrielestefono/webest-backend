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
        /** @var User|null $user */
        $user = $dto->user;

        if (! $user && $dto->token) {
            $user = PersonalAccessToken::findToken($dto->token)?->tokenable;
        }

        if (! $user) {
            throw new UnauthorizedException('Token inválido ou expirado.');
        }

        return $user;
    }
}
