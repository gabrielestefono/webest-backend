<?php

namespace App\Actions\Auth;

use App\Dto\Auth\VerifyEmailDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmailAction
{
    /**
     * Validate signature and mark user's email as verified.
     *
     * @return array<string, mixed>
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function handle(VerifyEmailDto $dto): array
    {
        if ($dto->expires < time()) {
            throw new BadRequestException('O link de verificação expirou.');
        }

        $key = (string) config('app.key');
        $expectedSignature = hash_hmac('sha256', "id={$dto->id}&hash={$dto->hash}&expires={$dto->expires}", $key);

        if (! hash_equals($expectedSignature, $dto->signature)) {
            throw new BadRequestException('Assinatura do link de verificação inválida.');
        }

        $user = User::find($dto->id);

        if (! $user) {
            throw new NotFoundException('Usuário não encontrado.');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $dto->hash)) {
            throw new BadRequestException('O e-mail não confere com o link de validação.');
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'verified' => true,
                'already_verified' => true,
            ];
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return [
            'verified' => true,
            'already_verified' => false,
        ];
    }
}

