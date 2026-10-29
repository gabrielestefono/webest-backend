<?php

namespace App\Dto\Auth;

use App\Models\User;

readonly class SendConfirmationEmailDto
{
    public function __construct(
        public User $user,
    ) {}
}

