<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReturnProfitAccountingTest extends TestCase
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

    public function test_return_deducts_sales_once_and_profit_margin_only(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'RET-75',
            'name' => 'Seventy Five Item',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 75,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 10]);

        $customer = Customer::query()->create([
            'name' => 'Cash Buyer',
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
            'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 75]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 75, 'condition' => 'sellable'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        Cache::flush();

        $summary = $this->withToken($this->token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();

        $this->assertEquals(75.0, $summary['weekly_revenue']);
        $this->assertEquals(75.0, $summary['weekly_customer_refunds']);
        $this->assertEquals(0.0, $summary['weekly_net_sales']);
        $this->assertEquals(25.0, $summary['weekly_gross_profit']);
        $this->assertEquals(0.0, $summary['weekly_profit']);
    }
}
