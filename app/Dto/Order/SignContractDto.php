<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class SignContractDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public bool $acceptedTerms,
    ) {}
}

