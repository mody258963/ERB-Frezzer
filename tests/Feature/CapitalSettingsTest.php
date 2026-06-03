<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\User;
use App\Enums\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapitalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        $this->adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');
    }

    public function test_admin_can_set_and_read_business_capital(): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/v1/settings/capital', [
                'capital_amount' => 100000,
                'reason' => 'Initial owner capital',
                'notes' => 'EGP operating capital',
            ])
            ->assertOk()
            ->assertJsonPath('capital_amount', 100000)
            ->assertJsonPath('currency', 'EGP')
            ->assertJsonPath('financing_snapshot.inventory_at_cost', 0);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital')
            ->assertOk()
            ->assertJsonPath('capital_amount', 100000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital/adjustments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_capital_appears_on_dashboard_summary(): void
    {
        $this->withToken($this->adminToken)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 100000,
        ])->assertOk();

        $summary = $this->withToken($this->adminToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();

        $this->assertEquals(100000.0, $summary['business_capital']);
        $this->assertEquals('EGP', $summary['capital_currency']);
    }

    public function test_financing_snapshot_reflects_inventory(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'CAP-1',
            'name' => 'Capital Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 10]);

        $this->withToken($this->adminToken)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 100000,
        ])->assertOk();

        $show = $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital')
            ->assertOk()
            ->json();

        $this->assertEquals(1000.0, $show['financing_snapshot']['inventory_at_cost']);
        $this->assertEquals(99000.0, $show['financing_snapshot']['estimated_available']);
    }

    public function test_non_admin_cannot_update_capital(): void
    {
        $branch = Branch::query()->firstOrFail();
        $manager = User::query()->create([
            'name' => 'Mgr',
            'email' => 'mgr@example.com',
            'password' => 'password123',
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'api')
            ->putJson('/api/v1/settings/capital', ['capital_amount' => 50000])
            ->assertForbidden();

        $this->actingAs($manager, 'api')
            ->getJson('/api/v1/settings/capital')
            ->assertOk();
    }
}
