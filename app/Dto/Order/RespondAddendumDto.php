<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class RespondAddendumDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public int $adjustmentId,
        public string $decision,
        public ?float $counterPrice = null,
        public ?string $counterNotes = null,
    ) {}
}

