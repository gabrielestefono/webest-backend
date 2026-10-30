<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class RespondProposalDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public string $decision,
        public ?float $counterPrice = null,
        public ?int $counterDays = null,
        public ?string $counterContent = null,
    ) {}
}

