<?php

namespace App\Transformers;

use App\Models\AuditLog;

final class AuditLogTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(AuditLog $log): array
    {
        $data = [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'old_value' => $log->old_value,
            'new_value' => $log->new_value,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toISOString(),
        ];

        if ($log->relationLoaded('user') && $log->user) {
            $data['user'] = UserTransformer::transform($log->user);
        }

        return $data;
    }
}
