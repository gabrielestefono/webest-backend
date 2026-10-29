<?php

namespace App\Dto\Auth;

readonly class VerifyEmailDto
{
    public function __construct(
        public int $id,
        public string $hash,
        public int $expires,
        public string $signature,
    ) {}
}

