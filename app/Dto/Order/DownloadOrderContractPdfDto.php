<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class DownloadOrderContractPdfDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
    ) {}
}

