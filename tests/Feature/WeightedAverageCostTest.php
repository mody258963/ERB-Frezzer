<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WeightedAverageCostTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        Cache::flush();

        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->branch = Branch::query()->firstOrFail();
    }

    public function test_two_purchases_blend_branch_average_cost_to_110(): void
    {
        $part = $this->createPart('WAC-1');
        $supplier = $this->createSupplier();

        $this->receivePurchase($supplier, $part, 10, 100);
        $this->receivePurchase($supplier, $part, 10, 120);

        $stock = Stock::query()
            ->where('part_id', $part->id)
            ->where('branch_id', $this->branch->id)
            ->firstOrFail();

        $this->assertSame(20, (int) $stock->quantity);
        $this->assertEquals(110.0, (float) $stock->average_cost);
        $this->assertEquals(110.0, (float) $part->fresh()->cost_price);
    }

    public function test_sale_snapshots_unit_cost_and_profit_ignores_later_average_changes(): void
    {
        $part = $this->createPart('WAC-2');
        $supplier = $this->createSupplier();
        $customer = Customer::query()->create([
            'name' => 'WAC Cash',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->receivePurchase($supplier, $part, 10, 100);

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 2, 'unit_price' => 200]],
        ])->assertCreated()->json('id');

        $line = InvoiceItem::query()->where('invoice_id', $invoiceId)->firstOrFail();
        $this->assertEquals(100.0, (float) $line->unit_cost);

        $this->receivePurchase($supplier, $part, 10, 150);

        Cache::flush();
        $summary = $this->withToken($this->token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertEquals(200.0, $summary['weekly_gross_profit']);
    }

    public function test_transfer_blends_destination_average_using_source_cost(): void
    {
        $branchB = Branch::query()->create([
            'name' => 'WAC Branch B',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $part = $this->createPart('WAC-3');
        $supplier = $this->createSupplier();

        $this->receivePurchase($supplier, $part, 10, 100);
        $this->receivePurchase($supplier, $part, 10, 120);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branchB->id,
            'quantity' => 10,
            'average_cost' => 100,
        ]);

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $branchB->id,
            'items' => [['part_id' => $part->id, 'quantity' => 5]],
        ])->assertCreated()->json('id');

        $this->withToken($this->token)
            ->patchJson("/api/v1/transfers/{$transferId}/complete")
            ->assertOk();

        $dest = Stock::query()
            ->where('part_id', $part->id)
            ->where('branch_id', $branchB->id)
            ->firstOrFail();

        $this->assertSame(15, (int) $dest->quantity);
        $this->assertEquals(103.33, round((float) $dest->average_cost, 2));
    }

    public function test_dashboard_stock_value_uses_weighted_average_not_manual_part_cost(): void
    {
        $part = Part::query()->create([
            'code' => 'WAC-4',
            'name' => 'WAC Value Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 250,
            'cost_price' => 999,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $supplier = $this->createSupplier();
        $this->receivePurchase($supplier, $part, 10, 50);

        Cache::flush();
        $summary = $this->withToken($this->token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();

        $this->assertEquals(500.0, $summary['total_stock_value_cost']);
        $this->assertEquals(50.0, (float) $part->fresh()->cost_price);
    }

    private function createPart(string $code): Part
    {
        return Part::query()->create([
            'code' => $code,
            'name' => $code,
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'WAC Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);
    }

    private function receivePurchase(Supplier $supplier, Part $part, int $qty, float $unitCost): void
    {
        $poId = (string) $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'immediate',
            'items' => [['part_id' => $part->id, 'quantity' => $qty, 'unit_cost' => $unitCost]],
        ])->assertCreated()->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/purchases/{$poId}/receive")->assertOk();
    }
}
