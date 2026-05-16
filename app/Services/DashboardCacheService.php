<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const SUMMARY_KEY = 'dashboard.summary';

    public function forgetSummary(): void
    {
        Cache::forget(self::SUMMARY_KEY);
    }

    public function keySummary(): string
    {
        return self::SUMMARY_KEY;
    }
}
