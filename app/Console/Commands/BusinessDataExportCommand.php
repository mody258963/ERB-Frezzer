<?php

namespace App\Console\Commands;

use App\Services\BusinessDataSnapshotService;
use Illuminate\Console\Command;

class BusinessDataExportCommand extends Command
{
    protected $signature = 'business-data:export
                            {--path= : Output JSON path (default: database/snapshots/business-data.json)}';

    protected $description = 'Export parts, stock, customers, suppliers, and finance data to JSON';

    public function handle(BusinessDataSnapshotService $snapshots): int
    {
        $path = $this->option('path') ?: BusinessDataSnapshotService::DEFAULT_PATH;

        $absolute = $snapshots->export($path);

        $this->info('Business data exported to: '.$absolute);
        $this->newLine();
        $this->table(['Table', 'Rows'], collect($snapshots->tableCounts($path))
            ->map(fn (int $count, string $table) => [$table, $count])
            ->values()
            ->all());

        $this->newLine();
        $this->comment('After migrate:fresh --seed, restore with: php artisan business-data:import');

        return self::SUCCESS;
    }
}
