<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogs
    ) {}

    public function record(
        User $user,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValue,
        ?array $newValue
    ): void {
        $this->auditLogs->record(
            $user,
            $action,
            $entityType,
            $entityId,
            $oldValue,
            $newValue,
            Request::ip()
        );
    }
}
