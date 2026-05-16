<?php

namespace App\Console\Commands;

use App\Services\DashboardQueryService;
use Illuminate\Console\Command;

class FrostpartsDashboardWarm extends Command
{
    protected $signature = 'frostparts:dashboard-warm';

    protected $description = 'Rebuild cached dashboard summary (Redis / default cache store)';

    public function handle(DashboardQueryService $dashboard): int
    {
        $dashboard->summary();

        return self::SUCCESS;
    }
}
