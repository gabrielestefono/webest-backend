<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class UpdateTurnMessageDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public int $messageId,
        public string $content,
    ) {}
}

