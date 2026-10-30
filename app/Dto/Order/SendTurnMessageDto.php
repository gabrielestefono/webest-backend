<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class SendTurnMessageDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public string $content,
    ) {}
}

