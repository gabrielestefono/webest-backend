<?php

namespace App\Actions\Auth;

use App\Dto\Auth\SendConfirmationEmailDto;

class SendConfirmationEmailAction
{
    /**
     * Send email verification notification to user.
     *
     * @return array<string, mixed>
     */
    public function handle(SendConfirmationEmailDto $dto): array
    {
        if ($dto->user->hasVerifiedEmail()) {
            return [
                'sent' => false,
                'already_verified' => true,
            ];
        }

        $dto->user->sendEmailVerificationNotification();

        return [
            'sent' => true,
            'already_verified' => false,
        ];
    }
}

