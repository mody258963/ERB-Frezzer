<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OwnerCashOutTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        Cache::flush();

        $this->adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($this->adminToken)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 100000,
            'reason' => 'Opening capital',
        ])->assertOk();

        $this->seedProfit(20000);
    }

    private function seedProfit(float $targetProfit): void
    {
        $branch = Branch::query()->firstOrFail();
        $sellPrice = 200;
        $costPrice = 100;
        $marginPerUnit = $sellPrice - $costPrice;
        $quantity = (int) ceil($targetProfit / $marginPerUnit);

        $part = Part::query()->create([
            'code' => 'CASHOUT-'.uniqid(),
            'name' => 'Cash Out Profit Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => $sellPrice,
            'cost_price' => $costPrice,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => $quantity + 10,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Cash Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->withToken($this->adminToken)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => $quantity],
            ],
        ])->assertCreated();
    }

    public function test_admin_can_record_owner_cash_out_from_profit_not_capital(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/v1/settings/capital/cash-out', [
            'amount' => 15000,
            'reason' => 'Owner personal withdrawal',
            'notes' => 'June draw',
        ]);

        $response->assertOk()
            ->assertJsonPath('cash_out.amount', 15000)
            ->assertJsonPath('capital.capital_amount', 100000)
            ->assertJsonPath('capital.profit_withdrawal.total_withdrawn', 15000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital')
            ->assertOk()
            ->assertJsonPath('capital_amount', 100000)
            ->assertJsonPath('profit_withdrawal.withdrawable_profit', 5000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('business_capital', 100000)
            ->assertJsonPath('total_owner_cash_outs', 15000);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital/cash-outs')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $adjustments = $this->withToken($this->adminToken)
            ->getJson('/api/v1/settings/capital/adjustments')
            ->assertOk()
            ->json('data');

        $cashOutAdj = collect($adjustments)->firstWhere('type', 'owner_cash_out');
        $this->assertNull($cashOutAdj);
    }

    public function test_cannot_cash_out_more_than_withdrawable_profit(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/settings/capital/cash-out', [
            'amount' => 25000,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cash out amount exceeds withdrawable profit (20,000.00). Owner draws are deducted from profit margin, not business capital.']);
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
