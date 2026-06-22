<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\BusinessDataSnapshotService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BusinessDataSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private string $snapshotPath = 'database/snapshots/test-business-data.json';

    protected function tearDown(): void
    {
        File::delete(base_path($this->snapshotPath));
        parent::tearDown();
    }

    public function test_export_and_import_round_trip_restores_catalog_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $branch = Branch::query()->firstOrFail();
        $categoryId = PartCategory::query()->where('key', 'compressor')->value('id');

        $part = Part::query()->create([
            'code' => 'SNAP-1',
            'name' => 'Snapshot Part',
            'category_id' => $categoryId,
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 25,
            'average_cost' => 40,
        ]);

        Customer::query()->create([
            'name' => 'Snapshot Customer',
            'type' => 'credit',
            'credit_limit' => 1000,
            'outstanding_balance' => 150,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        Supplier::query()->create([
            'name' => 'Snapshot Supplier',
            'total_debt' => 300,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        $service = app(BusinessDataSnapshotService::class);
        $service->export($this->snapshotPath);

        Part::query()->create([
            'code' => 'JUNK-1',
            'name' => 'Should be removed on import',
            'category_id' => $categoryId,
            'unit' => 'pc',
            'sell_price' => 1,
            'cost_price' => 1,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        $this->assertSame(2, Part::query()->count());

        $service->import($this->snapshotPath);

        $restoredPart = Part::query()->where('code', 'SNAP-1')->firstOrFail();
        $this->assertSame(1, Part::query()->count());
        $this->assertSame(25.0, (float) Stock::query()->where('part_id', $restoredPart->id)->value('quantity'));

        $customer = Customer::query()->where('name', 'Snapshot Customer')->firstOrFail();
        $this->assertSame('credit', $customer->type->value);
        $this->assertEquals(0.0, (float) $customer->outstanding_balance);

        $supplier = Supplier::query()->where('name', 'Snapshot Supplier')->firstOrFail();
        $this->assertEquals(0.0, (float) $supplier->total_debt);

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_import_rejects_legacy_full_snapshot_format(): void
    {
        $this->seed(DatabaseSeeder::class);

        File::ensureDirectoryExists(base_path('database/snapshots'));
        File::put(base_path($this->snapshotPath), json_encode([
            'schema' => 'legacy-full',
            'tables' => ['parts' => []],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('old full-data format');

        app(BusinessDataSnapshotService::class)->import($this->snapshotPath);
    }
}
