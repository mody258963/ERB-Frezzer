<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerCashOutTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($this->adminToken)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 100000,
            'reason' => 'Opening capital',
        ])->assertOk();
    }

    public function test_admin_can_record_owner_cash_out(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/v1/settings/capital/cash-out', [
            'amount' => 15000,
            'reason' => 'Owner personal withdrawal',
            'notes' => 'June draw',
        ]);

        $response->assertOk()
            ->assertJsonPath('cash_out.amount', 15000)
            ->assertJsonPath('capital.capital_amount', 85000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital')
            ->assertOk()
            ->assertJsonPath('capital_amount', 85000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('business_capital', 85000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital/cash-outs')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $adjustments = $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital/adjustments')
            ->assertOk()
            ->json('data');

        $cashOutAdj = collect($adjustments)->firstWhere('type', 'owner_cash_out');
        $this->assertNotNull($cashOutAdj);
        $this->assertEquals(-15000, $cashOutAdj['change_amount']);
    }

    public function test_cannot_cash_out_more_than_capital(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/settings/capital/cash-out', [
            'amount' => 150000,
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_cash_out(): void
    {
        $branch = Branch::query()->firstOrFail();
        $manager = User::query()->create([
            'name' => 'Mgr',
            'email' => 'mgr-cash@example.com',
            'password' => 'password123',
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'api')
            ->postJson('/api/v1/settings/capital/cash-out', ['amount' => 1000])
            ->assertForbidden();
    }
}
