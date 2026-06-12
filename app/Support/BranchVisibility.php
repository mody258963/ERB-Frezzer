<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BranchVisibility
{
    /**
     * Active branch filter for the current HTTP request.
     * - Non-admin: always their assigned branch.
     * - Admin + ?branch_id=: filter to that branch.
     * - Admin without param: null (all branches).
     */
    /**
     * Branch filter sent by the client: query param, optional header, or JSON body.
     */
    public static function requestedBranchId(?\Illuminate\Http\Request $request = null): ?string
    {
        $request = $request ?? request();

        if ($request === null) {
            return null;
        }

        $value = $request->query('branch_id')
            ?? $request->header('X-Branch-Id')
            ?? $request->input('branch_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function activeBranchId(?User $user = null): ?string
    {
        $request = request();

        if ($request?->attributes->has('resolved_branch_id')) {
            return $request->attributes->get('resolved_branch_id');
        }

        $user = $user ?? $request?->user();

        return self::resolveBranchId($user, self::requestedBranchId($request));
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scope(?User $user, Builder $query, string $branchColumn = 'branch_id'): Builder
    {
        $branchId = self::activeBranchId($user);

        if ($branchId !== null) {
            $query->where($branchColumn, $branchId);
        }

        return $query;
    }

    /**
     * Resolve branch filter: non-admin users are forced to their branch.
     */
    public static function resolveBranchId(?User $user, ?string $requestedBranchId): ?string
    {
        if ($user && $user->role !== UserRole::Admin && $user->branch_id) {
            return $user->branch_id;
        }

        return $requestedBranchId;
    }
}
