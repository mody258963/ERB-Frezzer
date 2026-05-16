<?php

namespace App\Transformers;

use App\Models\User;

final class UserTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'branch_id' => $user->branch_id,
            'is_active' => $user->is_active,
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
