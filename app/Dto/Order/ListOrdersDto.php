<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class ListOrdersDto
{
    public function __construct(
        public ?User $user,
        public ?string $statusFilter = null,
    ) {}
}

