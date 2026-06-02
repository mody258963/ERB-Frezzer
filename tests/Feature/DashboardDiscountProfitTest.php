<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardDiscountProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_discount_reduces_profit_not_gross_revenue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        Cache::flush();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'DISC-1',
            'name' => 'Discount Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 120,
            'cost_price' => 85,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 100]);

        $customer = Customer::query()->create([
            'name' => 'Cash C',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->withToken($token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'discount' => 200,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 20],
            ],
        ])->assertCreated();

        $summary = $this->withToken($token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();

        $this->assertEquals(2400.0, $summary['weekly_revenue']);
        $this->assertEquals(200.0, $summary['weekly_discount']);
        $this->assertEquals(2200.0, $summary['weekly_net_sales']);
        $this->assertEquals(700.0, $summary['weekly_gross_profit']);
        $this->assertEquals(500.0, $summary['weekly_profit']);
    }
}
