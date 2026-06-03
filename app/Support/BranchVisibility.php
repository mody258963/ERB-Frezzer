<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BranchVisibility
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scope(?User $user, Builder $query, string $branchColumn = 'branch_id'): Builder
    {
        if ($user && $user->role !== UserRole::Admin && $user->branch_id) {
            $query->where($branchColumn, $user->branch_id);
        }

        return $query;
    }

    /**
     * Resolve branch filter for reports: non-admin users are forced to their branch.
     */
    public static function resolveBranchId(?User $user, ?string $requestedBranchId): ?string
    {
        if ($user && $user->role !== UserRole::Admin && $user->branch_id) {
            return $user->branch_id;
        }

        return $requestedBranchId;
    }
}
