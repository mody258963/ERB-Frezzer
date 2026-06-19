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

    /**
     * Tables in FK-safe export order (parents before children).
     *
     * @var list<string>
     */
    private const EXPORT_TABLES = [
        'branches',
        'part_categories',
        'parts',
        'stock',
        'customers',
        'suppliers',
        'company_settings',
        'capital_adjustments',
        'owner_cash_outs',
        'saturday_settlements',
        'customer_payments',
        'purchase_orders',
        'purchase_order_items',
        'supplier_installments',
        'supplier_installment_payments',
        'invoices',
        'invoice_items',
        'returns',
        'return_items',
        'stock_transfers',
        'stock_transfer_items',
        'contra_settlements',
    ];

    /**
     * Reverse order for wiping before import.
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

    /** @var array<string, string> */
    private array $categoryMap = [];

    private ?string $adminUserId = null;

    public function export(string $path = self::DEFAULT_PATH): string
    {
        $absolute = base_path($path);
        File::ensureDirectoryExists(dirname($absolute));

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'app' => 'ERB-Frezzer',
            'tables' => [],
        ];

        foreach (self::EXPORT_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $payload['tables'][$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        }

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
        $tables = $payload['tables'] ?? [];

        if ($tables === []) {
            throw new \RuntimeException('Snapshot file has no table data.');
        }

        $this->adminUserId = (string) User::query()->where('email', 'admin@example.com')->value('id');
        if ($this->adminUserId === '') {
            throw new \RuntimeException('Admin user not found. Run: php artisan migrate:fresh --seed');
        }

        DB::transaction(function () use ($tables) {
            $this->withoutForeignKeyChecks(function () use ($tables) {
                $this->wipeBusinessTables();
                $this->importBranches($tables['branches'] ?? []);
                $this->importPartCategories($tables['part_categories'] ?? []);
                $this->importParts($tables['parts'] ?? []);
                $this->importSimple('stock', $tables['stock'] ?? [], ['part_id', 'branch_id']);
                $this->importCustomers($tables['customers'] ?? []);
                $this->importSimple('suppliers', $tables['suppliers'] ?? [], ['branch_id']);
                $this->importSimple('company_settings', $tables['company_settings'] ?? [], [], remapUser: ['updated_by']);
                $this->importSimple('capital_adjustments', $tables['capital_adjustments'] ?? [], ['branch_id'], remapUser: ['created_by']);
                $this->importSimple('owner_cash_outs', $tables['owner_cash_outs'] ?? [], ['branch_id'], remapUser: ['created_by']);
                $this->importSimple('saturday_settlements', $tables['saturday_settlements'] ?? [], ['customer_id'], remapUser: ['created_by']);
                $this->importSimple('customer_payments', $tables['customer_payments'] ?? [], ['customer_id'], remapUser: ['created_by']);
                $this->importSimple('purchase_orders', $tables['purchase_orders'] ?? [], ['supplier_id', 'branch_id'], remapUser: ['created_by']);
                $this->importSimple('purchase_order_items', $tables['purchase_order_items'] ?? [], ['purchase_order_id', 'part_id']);
                $this->importSimple('supplier_installments', $tables['supplier_installments'] ?? [], ['purchase_order_id']);
                $this->importSimple('supplier_installment_payments', $tables['supplier_installment_payments'] ?? [], ['installment_id'], remapUser: ['created_by']);
                $this->importSimple('invoices', $tables['invoices'] ?? [], ['customer_id', 'branch_id', 'settlement_id'], remapUser: ['created_by']);
                $this->importSimple('invoice_items', $tables['invoice_items'] ?? [], ['invoice_id', 'part_id']);
                $this->importSimple('returns', $tables['returns'] ?? [], ['customer_id', 'supplier_id', 'branch_id', 'reference_id'], remapUser: ['created_by', 'approved_by']);
                $this->importSimple('return_items', $tables['return_items'] ?? [], ['return_id', 'part_id']);
                $this->importSimple('stock_transfers', $tables['stock_transfers'] ?? [], ['from_branch_id', 'to_branch_id'], remapUser: ['created_by']);
                $this->importSimple('stock_transfer_items', $tables['stock_transfer_items'] ?? [], ['transfer_id', 'part_id']);
                $this->importSimple('contra_settlements', $tables['contra_settlements'] ?? [], ['customer_id', 'supplier_id'], remapUser: ['created_by']);
            });
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importBranches(array $rows): void
    {
        foreach ($rows as $row) {
            $oldId = (string) $row['id'];
            $existing = Branch::query()->where('name', $row['name'])->first();

            if ($existing) {
                $existing->fill([
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'capital_amount' => $row['capital_amount'] ?? 0,
                ]);
                $existing->save();
                $this->branchMap[$oldId] = $existing->id;
            } else {
                Branch::query()->insert([
                    'id' => $oldId,
                    'name' => $row['name'],
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'capital_amount' => $row['capital_amount'] ?? 0,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]);
                $this->branchMap[$oldId] = $oldId;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPartCategories(array $rows): void
    {
        foreach ($rows as $row) {
            $oldId = (string) $row['id'];
            $category = PartCategory::query()->firstOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'] ?? 0,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]
            );
            $this->categoryMap[$oldId] = $category->id;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importParts(array $rows): void
    {
        foreach ($rows as $row) {
            $row['category_id'] = $this->categoryMap[(string) $row['category_id']] ?? $row['category_id'];
            if (! empty($row['branch_id'])) {
                $row['branch_id'] = $this->branchMap[(string) $row['branch_id']] ?? $row['branch_id'];
            }
            DB::table('parts')->insert($row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $branchFkColumns
     * @param  list<string>  $remapUser
     */
    private function importSimple(
        string $table,
        array $rows,
        array $branchFkColumns = [],
        array $remapUser = [],
    ): void {
        if ($rows === [] || ! Schema::hasTable($table)) {
            return;
        }

        foreach ($rows as $row) {
            foreach ($branchFkColumns as $column) {
                if (! empty($row[$column]) && isset($this->branchMap[(string) $row[$column]])) {
                    $row[$column] = $this->branchMap[(string) $row[$column]];
                }
            }

            foreach ($remapUser as $column) {
                if (! empty($row[$column])) {
                    $row[$column] = $this->adminUserId;
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

            DB::table('customers')->insert($row);
        }

        foreach ($rows as $row) {
            if (empty($row['linked_supplier_id'])) {
                continue;
            }

            DB::table('customers')
                ->where('id', $row['id'])
                ->update(['linked_supplier_id' => $row['linked_supplier_id']]);
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
