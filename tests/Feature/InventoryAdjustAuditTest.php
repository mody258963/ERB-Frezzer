<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_adjust_writes_audit_with_stock_uuid_entity_id(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'AUD-001',
            'name' => 'Audit Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
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
            'quantity' => 0,
        ]);

        $this->withToken($token)->postJson('/api/v1/inventory/adjust', [
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity_delta' => 50,
        ])->assertOk();

        $stockId = (string) Stock::query()
            ->where('part_id', $part->id)
            ->where('branch_id', $branch->id)
            ->value('id');

        $log = AuditLog::query()
            ->where('action', 'inventory.adjust')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($stockId, $log->entity_id);
        $this->assertSame('stock', $log->entity_type);
    }
}
