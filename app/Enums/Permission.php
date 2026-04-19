<?php

namespace App\Enums;

enum Permission: string
{
    case ViewAdminDashboard = 'view-admin-dashboard';
    case ManageOrders = 'manage-orders';
    case ManageProducts = 'manage-products';
    case ManageUsers = 'manage-users';
    case ManageProjects = 'manage-projects';
    case ManageChangeRequests = 'manage-change-requests';
    case ViewClientDashboard = 'view-client-dashboard';
    case ViewMyProjects = 'view-my-projects';
    case SubmitProposals = 'submit-proposals';
    case AcceptProposals = 'accept-proposals';
    case SubmitChangeRequests = 'submit-change-requests';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function adminDefaults(): array
    {
        return self::values();
    }

    /**
     * @return list<string>
     */
    public static function clientDefaults(): array
    {
        return [
            self::ViewClientDashboard->value,
            self::ViewMyProjects->value,
            self::SubmitProposals->value,
            self::AcceptProposals->value,
            self::SubmitChangeRequests->value,
        ];
    }
}
