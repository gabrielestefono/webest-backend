<?php

namespace App\Dto\Auth;

readonly class RegisterDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}

