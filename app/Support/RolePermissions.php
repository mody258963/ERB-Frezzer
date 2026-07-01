<?php

namespace App\Support;

use App\Enums\UserRole;

final class RolePermissions
{
    public static function canViewDashboard(UserRole $role): bool
    {
        return in_array($role, [UserRole::Admin, UserRole::Manager], true);
    }

    public static function canViewCapital(UserRole $role): bool
    {
        return in_array($role, [UserRole::Admin, UserRole::Manager], true);
    }

    public static function canCashOutProfit(UserRole $role): bool
    {
        return $role === UserRole::Admin;
    }

    public static function canViewReports(UserRole $role): bool
    {
        return in_array($role, [UserRole::Admin, UserRole::Manager], true);
    }

    public static function canPaySuppliers(UserRole $role): bool
    {
        return in_array($role, [
            UserRole::Admin,
            UserRole::Manager,
            UserRole::Salesperson,
            UserRole::Warehouse,
        ], true);
    }

    public static function canCollectCustomerPayments(UserRole $role): bool
    {
        return in_array($role, [
            UserRole::Admin,
            UserRole::Manager,
            UserRole::Salesperson,
        ], true);
    }
}
