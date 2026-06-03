<?php

namespace App\Transformers;

use App\Enums\UserRole;
use App\Models\User;

final class UserTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(User $user): array
    {
        $isAdmin = $user->role === UserRole::Admin;

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'branch_id' => $user->branch_id,
            'is_active' => $user->is_active,
            'can_select_branch' => $isAdmin || $user->branch_id === null,
            'accessible_branch_ids' => $isAdmin || $user->branch_id === null
                ? null
                : [$user->branch_id],
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
