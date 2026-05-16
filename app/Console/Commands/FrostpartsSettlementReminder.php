<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FrostpartsSettlementReminder extends Command
{
    protected $signature = 'frostparts:settlement-reminder';

    protected $description = 'Notify managers of upcoming Saturday credit settlements';

    public function handle(SaturdaySettlementRepositoryInterface $settlements): int
    {
        $totals = $settlements->upcomingTotals();
        Log::info('Settlement reminder', ['customers' => $totals->toArray()]);

        return self::SUCCESS;
    }
}
