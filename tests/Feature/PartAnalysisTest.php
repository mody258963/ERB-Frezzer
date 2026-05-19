<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Part;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_analysis_returns_inventory_and_sales_metrics(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $token = (string) $login->json('token');

        $branchId = (string) Branch::query()->value('id');

        $part = $this->withToken($token)->postJson('/api/v1/parts', [
            'code' => 'ANL-001',
            'name' => 'Analysis Part',
            'category' => 'Compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 60,
            'min_stock' => 10,
            'is_active' => true,
        ]);
        $part->assertCreated();
        $partId = (string) $part->json('id');

        $this->withToken($token)->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $branchId,
            'quantity_delta' => 50,
        ])->assertOk();

        $customer = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Analysis Customer',
            'type' => 'cash',
        ]);
        $customerId = (string) $customer->json('id');

        $this->withToken($token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'payment_type' => 'cash',
            'items' => [['part_id' => $partId, 'quantity' => 4]],
        ])->assertCreated();

        $response = $this->withToken($token)->getJson("/api/v1/parts/{$partId}/analysis");
        $response->assertOk()
            ->assertJsonPath('part.id', $partId)
            ->assertJsonPath('inventory.total_quantity', 46)
            ->assertJsonPath('sales.units_sold', 4)
            ->assertJsonPath('sales.revenue', 400)
            ->assertJsonPath('sales.invoice_count', 1)
            ->assertJsonStructure([
                'part',
                'period',
                'inventory' => ['by_branch', 'is_below_min_stock'],
                'sales' => ['gross_profit', 'gross_margin_percent'],
                'purchases',
                'returns',
                'movements' => ['by_type', 'recent'],
                'sales_by_month',
            ]);
    }

    public function test_part_analysis_returns_404_for_missing_part(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->withToken((string) $login->json('token'))
            ->getJson('/api/v1/parts/'.fake()->uuid().'/analysis')
            ->assertNotFound();
    }
}
