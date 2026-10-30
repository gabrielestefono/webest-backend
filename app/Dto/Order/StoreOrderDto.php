<?php

namespace App\Dto\Order;

use App\Models\User;
use Illuminate\Http\UploadedFile;

readonly class StoreOrderDto
{
    /**
     * @param  list<int>  $productIds
     * @param  list<UploadedFile>  $attachments
     */
    public function __construct(
        public ?User $user,
        public string $title,
        public string $description,
        public ?string $figmaUrl,
        public array $productIds,
        public ?string $initialMessage,
        public array $attachments,
    ) {}
}
