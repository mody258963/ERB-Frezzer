<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
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

    public function test_export_and_import_round_trip_restores_business_data(): void
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

        $branch->capital_amount = 100000;
        $branch->save();

        $service = app(BusinessDataSnapshotService::class);
        $service->export($this->snapshotPath);

        $this->assertGreaterThan(0, Part::query()->count());

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
        $this->assertSame(150.0, (float) Customer::query()->where('name', 'Snapshot Customer')->value('outstanding_balance'));
        $this->assertSame(300.0, (float) Supplier::query()->where('name', 'Snapshot Supplier')->value('total_debt'));
        $this->assertEquals(100000.0, (float) Branch::query()->where('name', 'Main Branch')->value('capital_amount'));
    }
}
