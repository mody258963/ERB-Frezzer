<?php

namespace App\Console\Commands;

use App\Services\BusinessDataSnapshotService;
use Illuminate\Console\Command;

class BusinessDataImportCommand extends Command
{
    protected $signature = 'business-data:import
                            {--path= : JSON snapshot path (default: database/snapshots/business-data.json)}
                            {--force : Skip confirmation}';

    protected $description = 'Import catalog snapshot (parts, stock, customers, suppliers) after migrate:fresh --seed';

    public function handle(BusinessDataSnapshotService $snapshots): int
    {
        $path = $this->option('path') ?: BusinessDataSnapshotService::DEFAULT_PATH;
        $absolute = base_path($path);

        if (! file_exists($absolute)) {
            $this->error("Snapshot not found: {$absolute}");
            $this->comment('Export first: php artisan business-data:export');

            return self::FAILURE;
        }

        $counts = $snapshots->tableCounts($path);
        $this->table(['Table', 'Rows in snapshot'], collect($counts)
            ->map(fn (int $count, string $table) => [$table, $count])
            ->values()
            ->all());

        if (! $this->option('force') && ! $this->confirm('This will REPLACE current business data. Continue?', false)) {
            $this->warn('Import cancelled.');

            return self::FAILURE;
        }

        try {
            $snapshots->import($path);
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Business data imported successfully.');

        return self::SUCCESS;
    }
}
