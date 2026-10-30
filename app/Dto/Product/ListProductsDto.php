<?php

namespace App\Dto\Product;

use App\Models\User;

readonly class ListProductsDto
{
    public function __construct(
        public ?User $user,
    ) {}
}

