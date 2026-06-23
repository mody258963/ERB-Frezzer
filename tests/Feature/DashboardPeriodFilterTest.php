<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardPeriodFilterTest extends TestCase
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

    public function test_dashboard_summary_filters_by_day_week_and_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 12:00:00'));

        [$customerId, $partId] = $this->seedSaleFixtures();

        $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 2]],
        ])->assertCreated();

        Cache::flush();

        $day = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=day')
            ->assertOk()
            ->json();

        $this->assertSame('day', $day['period']['key']);
        $this->assertEquals(400.0, $day['period_revenue']);
        $this->assertEquals(400.0, $day['weekly_revenue']);

        $week = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=week')
            ->assertOk()
            ->json();

        $this->assertSame('week', $week['period']['key']);
        $this->assertEquals(400.0, $week['period_revenue']);

        $month = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=month')
            ->assertOk()
            ->json();

        $this->assertSame('month', $month['period']['key']);
        $this->assertEquals(400.0, $month['period_revenue']);

        Carbon::setTestNow();
    }

    public function test_dashboard_day_filter_excludes_sales_outside_selected_date(): void
    {
        [$customerId, $partId] = $this->seedSaleFixtures();

        $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->assertCreated();

        Invoice::query()->update([
            'created_at' => now()->subMonth()->startOfMonth(),
        ]);

        Cache::flush();

        $today = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=day')
            ->assertOk()
            ->json();

        $this->assertEquals(0.0, $today['period_revenue']);

        $lastMonth = now()->subMonth()->startOfMonth()->toDateString();

        $anchored = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=month&date='.$lastMonth)
            ->assertOk()
            ->json();

        $this->assertSame('month', $anchored['period']['key']);
        $this->assertEquals($lastMonth, $anchored['period']['anchor_date']);
        $this->assertEquals(200.0, $anchored['period_revenue']);
    }

    public function test_dashboard_sales_and_cash_endpoints_accept_period_filter(): void
    {
        [$customerId, $partId] = $this->seedSaleFixtures();

        $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->assertCreated();

        Cache::flush();

        $sales = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/sales?period=day')
            ->assertOk()
            ->json();

        $this->assertSame('day', $sales['period']['key']);
        $this->assertEquals(200.0, $sales['totals']['revenue']);

        $cash = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/cash?period=day')
            ->assertOk()
            ->json();

        $this->assertSame('day', $cash['period']['key']);
        $this->assertEquals(200.0, $cash['period_cash_in_realized']);
    }

    public function test_dashboard_week_uses_business_week_monday_nine_to_friday_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 12:00:00'));

        [$customerId, $partId] = $this->seedSaleFixtures();

        $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->assertCreated();

        Invoice::query()->update(['created_at' => Carbon::parse('2026-06-15 08:00:00')]);

        $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->assertCreated();

        Cache::flush();

        $week = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary?period=week&date=2026-06-18')
            ->assertOk()
            ->json();

        $this->assertSame('week', $week['period']['key']);
        $this->assertStringContainsString('2026-06-15T09:00:00', $week['period']['from']);
        $this->assertStringContainsString('2026-06-20T23:59:59', $week['period']['to']);
        $this->assertEquals(200.0, $week['period_revenue']);

        Carbon::setTestNow();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedSaleFixtures(): array
    {
        $part = Part::query()->create([
            'code' => 'PERIOD-1',
            'name' => 'Period Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 20,
            'average_cost' => 100,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Period Cash Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        return [$customer->id, $part->id];
    }
}
