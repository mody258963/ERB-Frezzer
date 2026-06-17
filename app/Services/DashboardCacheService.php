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
        $version = (int) Cache::get('dashboard.summary.version', 1);
        Cache::forever('dashboard.summary.version', $version + 1);
    }

    public function keySummary(?string $branchId = null, ?string $periodSuffix = null): string
    {
        $version = (int) Cache::get('dashboard.summary.version', 1);
        $suffix = $periodSuffix ?? 'week.'.now()->toDateString();

        return self::SUMMARY_PREFIX.$version.'.'.($branchId ?? 'all').'.'.$suffix;
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
