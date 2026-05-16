<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use App\Models\User;

interface AuditLogRepositoryInterface
{
    public function record(
        User $user,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?string $ipAddress
    ): AuditLog;
}
