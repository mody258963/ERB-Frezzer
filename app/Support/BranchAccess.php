<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class BranchAccess
{
    public static function assertUserMayUseBranch(User $user, string $branchId): void
    {
        if ($user->role === UserRole::Admin) {
            return;
        }

        if ($user->branch_id !== null && (string) $user->branch_id !== (string) $branchId) {
            throw new \InvalidArgumentException('You may only create records for your assigned branch.');
        }
    }
}
