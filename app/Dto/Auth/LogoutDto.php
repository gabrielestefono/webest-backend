<?php

namespace App\Dto\Auth;

use App\Models\User;

readonly class LogoutDto
{
    public function __construct(
        public ?string $token = null,
        public ?User $user = null,
    ) {}
}

