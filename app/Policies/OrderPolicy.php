<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($this->isProvider($user)) {
            return true;
        }

        return $order->user_id === $user->id;
    }

    public function interactAsClient(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function isProvider(User $user): bool
    {
        return $user->hasRole('admin') || $user->can(Permission::ManageOrders->value);
    }
}

