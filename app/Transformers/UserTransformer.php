<?php

namespace App\Transformers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Support\RolePermissions;
use App\Models\User;

final class UserTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(User $user): array
    {
        $isAdmin = $user->role === UserRole::Admin;

        $canAccessAllBranches = $isAdmin;

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'branch_id' => $user->branch_id,
            'is_active' => $user->is_active,
            'can_access_all_branches' => $canAccessAllBranches,
            'can_select_branch' => $canAccessAllBranches || $user->branch_id === null,
            'accessible_branch_ids' => $canAccessAllBranches
                ? Branch::query()->where('is_active', true)->orderBy('name')->pluck('id')->all()
                : ($user->branch_id ? [$user->branch_id] : null),
            'can_view_dashboard' => RolePermissions::canViewDashboard($user->role),
            'can_view_capital' => RolePermissions::canViewCapital($user->role),
            'can_view_reports' => RolePermissions::canViewReports($user->role),
            'can_cash_out_profit' => RolePermissions::canCashOutProfit($user->role),
            'can_pay_suppliers' => RolePermissions::canPaySuppliers($user->role),
            'can_collect_customer_payments' => RolePermissions::canCollectCustomerPayments($user->role),
            'can_approve_returns' => RolePermissions::canApproveReturns($user->role),
            'can_create_purchases' => RolePermissions::canCreatePurchases($user->role),
        ];

        if ($user->relationLoaded('branch') && $user->branch) {
            $data['branch'] = [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
            ];
        }

        return $data;
    }
}
