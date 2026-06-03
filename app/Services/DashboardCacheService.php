<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const SUMMARY_PREFIX = 'dashboard.summary.';

    public function forgetSummary(?string $branchId = null): void
    {
        Cache::forget($this->keySummary($branchId));
    }

    public function forgetAllSummaries(): void
    {
        Cache::forget($this->keySummary(null));
        foreach (Cache::get('dashboard.summary.branches', []) as $branchId) {
            Cache::forget($this->keySummary($branchId));
        }
    }

    public function keySummary(?string $branchId = null): string
    {
        return self::SUMMARY_PREFIX.($branchId ?? 'all');
    }

    public function rememberBranchKey(string $branchId): void
    {
        $keys = Cache::get('dashboard.summary.branches', []);
        if (! in_array($branchId, $keys, true)) {
            $keys[] = $branchId;
            Cache::forever('dashboard.summary.branches', $keys);
        }
    }
}
