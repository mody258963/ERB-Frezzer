<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

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
    }

    public function test_financial_report_includes_profit_and_refunds_after_return(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'FIN-1',
            'name' => 'Financial Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        \App\Models\Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 50,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Fin Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 200]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 200, 'condition' => 'sellable'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $report = $this->withToken($this->token)
            ->getJson("/api/v1/reports/financial?from={$from}&to={$to}")
            ->assertOk()
            ->json();

        $this->assertEquals(200.0, $report['totals']['revenue']);
        $this->assertEquals(200.0, $report['totals']['customer_refunds']);
        $this->assertEquals(0.0, $report['totals']['net_sales']);
        $this->assertEquals(100.0, $report['totals']['gross_profit']);
        $this->assertEquals(0.0, $report['totals']['profit']);
        $this->assertEquals(1, $report['returns']['customer_count']);
    }

    public function test_dashboard_sales_includes_totals_after_return(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'DASH-S-1',
            'name' => 'Dash Sales Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 150,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        \App\Models\Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 20,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Dash Sales C',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 2]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 150, 'condition' => 'sellable'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        Cache::flush();

        $sales = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/sales')
            ->assertOk()
            ->json();

        $this->assertEquals(300.0, $sales['totals']['revenue']);
        $this->assertEquals(150.0, $sales['totals']['customer_refunds']);
        $this->assertArrayHasKey('profit', $sales['totals']);
    }
}
