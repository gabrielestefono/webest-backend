<?php

namespace App\Dto\Order;

use App\Models\User;

readonly class DownloadOrderAttachmentDto
{
    public function __construct(
        public ?User $user,
        public int $orderId,
        public int $attachmentId,
    ) {}
}

