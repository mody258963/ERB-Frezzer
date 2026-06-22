<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\PartCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BusinessDataSnapshotService
{
    public const DEFAULT_PATH = 'database/snapshots/business-data.json';

    public const SCHEMA = 'catalog-only-v2';

    /**
     * Catalog-only export: parts, warehouse stock, customers (name + type), suppliers.
     * Users, branches, and categories come from migrate:fresh --seed.
     *
     * @var list<string>
     */
    private const EXPORT_TABLES = [
        'parts',
        'stock',
        'customers',
        'suppliers',
    ];

    /**
     * Wipe order: transactions first, then catalog rows we re-import.
     *
     * @var list<string>
     */
    private const WIPE_TABLES = [
        'contra_settlements',
        'stock_transfer_items',
        'stock_transfers',
        'return_items',
        'returns',
        'invoice_items',
        'invoices',
        'supplier_installment_payments',
        'supplier_installments',
        'purchase_order_items',
        'purchase_orders',
        'customer_payments',
        'saturday_settlements',
        'owner_cash_outs',
        'capital_adjustments',
        'company_settings',
        'stock',
        'parts',
        'customers',
        'suppliers',
    ];

    /** @var array<string, string> */
    private array $branchMap = [];

    private ?string $adminUserId = null;

    public function export(string $path = self::DEFAULT_PATH): string
    {
        $absolute = base_path($path);
        File::ensureDirectoryExists(dirname($absolute));

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'app' => 'ERB-Frezzer',
            'schema' => self::SCHEMA,
            'branch_mapping' => DB::table('branches')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($row) => ['id' => (string) $row->id, 'name' => $row->name])
                ->values()
                ->all(),
            'tables' => [
                'parts' => $this->exportParts(),
                'stock' => $this->exportStock(),
                'customers' => $this->exportCustomers(),
                'suppliers' => $this->exportSuppliers(),
            ],
        ];

        File::put(
            $absolute,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );

        return $absolute;
    }

    public function import(string $path = self::DEFAULT_PATH): void
    {
        $absolute = base_path($path);

        if (! File::exists($absolute)) {
            throw new \RuntimeException("Snapshot not found: {$absolute}");
        }

        $payload = json_decode(File::get($absolute), true, 512, JSON_THROW_ON_ERROR);
        $schema = $payload['schema'] ?? 'legacy-full';

        if ($schema !== self::SCHEMA) {
            throw new \RuntimeException(
                'Snapshot uses an old full-data format. Re-export with: php artisan business-data:export',
            );
        }

        $tables = $payload['tables'] ?? [];

        if ($tables === []) {
            throw new \RuntimeException('Snapshot file has no table data.');
        }

        $this->adminUserId = (string) User::query()->where('email', 'admin@example.com')->value('id');
        if ($this->adminUserId === '') {
            throw new \RuntimeException('Admin user not found. Run: php artisan migrate:fresh --seed');
        }

        DB::transaction(function () use ($tables, $payload) {
            $this->withoutForeignKeyChecks(function () use ($tables, $payload) {
                $this->wipeBusinessTables();
                $this->initBranchMap($payload['branch_mapping'] ?? []);
                $this->importParts($tables['parts'] ?? []);
                $this->importSimple('stock', $tables['stock'] ?? [], ['part_id', 'branch_id']);
                $this->importCustomers($tables['customers'] ?? []);
                $this->importSuppliers($tables['suppliers'] ?? []);
            });
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportParts(): array
    {
        if (! Schema::hasTable('parts')) {
            return [];
        }

        return DB::table('parts')
            ->join('part_categories', 'part_categories.id', '=', 'parts.category_id')
            ->select([
                'parts.id',
                'parts.code',
                'parts.name',
                'part_categories.key as category_key',
                'parts.unit',
                'parts.sell_price',
                'parts.cost_price',
                'parts.min_stock',
                'parts.is_active',
                'parts.branch_id',
                'parts.image_path',
                'parts.created_at',
                'parts.updated_at',
            ])
            ->orderBy('parts.code')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportStock(): array
    {
        if (! Schema::hasTable('stock')) {
            return [];
        }

        return DB::table('stock')
            ->select(['id', 'part_id', 'branch_id', 'quantity', 'average_cost', 'created_at', 'updated_at'])
            ->orderBy('part_id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCustomers(): array
    {
        if (! Schema::hasTable('customers')) {
            return [];
        }

        return DB::table('customers')
            ->select([
                'id',
                'name',
                'type',
                'phone',
                'address',
                'credit_limit',
                'branch_id',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($row) {
                $data = (array) $row;
                $data['outstanding_balance'] = '0.00';
                $data['last_settled_at'] = null;
                $data['linked_supplier_id'] = null;

                if (($data['type'] ?? '') === 'cash') {
                    $data['credit_limit'] = '0.00';
                }

                return $data;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportSuppliers(): array
    {
        if (! Schema::hasTable('suppliers')) {
            return [];
        }

        return DB::table('suppliers')
            ->select([
                'id',
                'name',
                'phone',
                'address',
                'branch_id',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($row) {
                $data = (array) $row;
                $data['total_debt'] = '0.00';

                return $data;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: string, name: string}>  $mapping
     */
    private function initBranchMap(array $mapping): void
    {
        $this->branchMap = [];

        foreach ($mapping as $row) {
            $branch = Branch::query()->where('name', $row['name'])->first();
            if ($branch) {
                $this->branchMap[(string) $row['id']] = $branch->id;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importParts(array $rows): void
    {
        foreach ($rows as $row) {
            $categoryKey = $row['category_key'] ?? null;
            unset($row['category_key']);

            if ($categoryKey !== null) {
                $categoryId = PartCategory::query()->where('key', $categoryKey)->value('id');
                if ($categoryId === null) {
                    throw new \RuntimeException("Part category not found in seed: {$categoryKey}");
                }
                $row['category_id'] = $categoryId;
            }

            if (! empty($row['branch_id'])) {
                $row['branch_id'] = $this->branchMap[(string) $row['branch_id']] ?? $row['branch_id'];
            }

            DB::table('parts')->insert($row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $fkColumns
     */
    private function importSimple(string $table, array $rows, array $fkColumns = []): void
    {
        if ($rows === [] || ! Schema::hasTable($table)) {
            return;
        }

        foreach ($rows as $row) {
            foreach ($fkColumns as $column) {
                if (! empty($row[$column]) && isset($this->branchMap[(string) $row[$column]])) {
                    $row[$column] = $this->branchMap[(string) $row[$column]];
                }
            }

            DB::table($table)->insert($row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importCustomers(array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['branch_id'])) {
                $row['branch_id'] = $this->branchMap[(string) $row['branch_id']] ?? $row['branch_id'];
            }

            $row['outstanding_balance'] = '0.00';
            $row['last_settled_at'] = null;
            $row['linked_supplier_id'] = null;

            if (($row['type'] ?? '') === 'cash') {
                $row['credit_limit'] = '0.00';
            }

            DB::table('customers')->insert($row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importSuppliers(array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['branch_id'])) {
                $row['branch_id'] = $this->branchMap[(string) $row['branch_id']] ?? $row['branch_id'];
            }

            $row['total_debt'] = '0.00';

            DB::table('suppliers')->insert($row);
        }
    }

    private function wipeBusinessTables(): void
    {
        foreach (self::WIPE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    private function withoutForeignKeyChecks(callable $callback): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            $callback();
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    /**
     * @return array<string, int>
     */
    public function tableCounts(string $path = self::DEFAULT_PATH): array
    {
        $absolute = base_path($path);

        if (! File::exists($absolute)) {
            return [];
        }

        $payload = json_decode(File::get($absolute), true);
        $counts = [];

        foreach ($payload['tables'] ?? [] as $table => $rows) {
            $counts[$table] = is_array($rows) ? count($rows) : 0;
        }

        return $counts;
    }
}
