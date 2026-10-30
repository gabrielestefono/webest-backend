<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class ShowOrderDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
    ) {}
}

