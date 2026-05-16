<?php

namespace App\Support;

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
        if ($user?->branch_id) {
            $query->where($branchColumn, $user->branch_id);
        }

        return $query;
    }
}
