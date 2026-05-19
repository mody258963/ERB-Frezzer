<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartSalesChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_parts_sales_chart_returns_monthly_series(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $token = (string) $login->json('token');
        $branchId = (string) Branch::query()->value('id');
        $year = (int) now()->format('Y');

        $part = $this->withToken($token)->postJson('/api/v1/parts', [
            'code' => 'CHT-1',
            'name' => 'Chart Part A',
            'category' => 'Compressor',
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 20,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $partId = (string) $part->json('id');

        $this->withToken($token)->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $branchId,
            'quantity_delta' => 100,
        ])->assertOk();

        $customer = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Chart Customer',
            'type' => 'cash',
        ]);

        $this->withToken($token)->postJson('/api/v1/invoices', [
            'customer_id' => (string) $customer->json('id'),
            'branch_id' => $branchId,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 10]],
        ])->assertCreated();

        $response = $this->withToken($token)->getJson("/api/v1/reports/parts-sales-chart?year={$year}&limit=5");
        $response->assertOk()
            ->assertJsonPath('year', $year)
            ->assertJsonCount(12, 'months')
            ->assertJsonPath('series.0.part_id', $partId)
            ->assertJsonPath('series.0.total_units_sold', 10)
            ->assertJsonStructure([
                'year',
                'months',
                'series' => [
                    ['part_id', 'code', 'name', 'total_units_sold', 'total_revenue', 'by_month'],
                ],
            ]);
    }
}
