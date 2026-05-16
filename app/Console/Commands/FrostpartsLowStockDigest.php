<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FrostpartsLowStockDigest extends Command
{
    protected $signature = 'frostparts:low-stock-digest';

    protected $description = 'Report all parts below minimum stock by branch';

    public function handle(StockRepositoryInterface $stock): int
    {
        $rows = $stock->lowStockByBranch();
        Log::info('Low stock digest', ['rows' => $rows->take(200)]);

        return self::SUCCESS;
    }
}
