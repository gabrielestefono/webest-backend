<?php

namespace App\Dto\Dashboard;

use App\Models\User;

readonly class DashboardOverviewDto
{
    public function __construct(
        public ?User $user,
    ) {}
}

