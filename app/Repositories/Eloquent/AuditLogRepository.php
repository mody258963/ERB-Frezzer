<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function record(
        User $user,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?string $ipAddress
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
