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
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_admin_can_list_active_branches_and_create_branch_user(): void
    {
        $branchB = Branch::query()->create([
            'name' => 'Branch B',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/branches/active')
            ->assertOk()
            ->assertJsonCount(2);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/users', [
                'name' => 'Sales B',
                'email' => 'salesb@example.com',
                'password' => 'password123',
                'role' => UserRole::Salesperson->value,
                'branch_id' => $branchB->id,
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'salesb@example.com')
            ->assertJsonPath('branch_id', $branchB->id);
    }

    public function test_admin_can_sell_in_any_branch_branch_user_cannot(): void
    {
        $main = Branch::query()->firstOrFail();
        $branchB = Branch::query()->create([
            'name' => 'Branch B2',
            'is_active' => true,
        ]);

        $part = Part::query()->create([
            'code' => 'BR-P1',
            'name' => 'Branch Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 20,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $main->id, 'quantity' => 10]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branchB->id, 'quantity' => 10]);

        $customer = Customer::query()->create([
            'name' => 'Branch Cust',
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
            'branch_id' => $branchB->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->withToken($this->adminToken)->postJson('/api/v1/users', [
            'name' => 'Main Sales',
            'email' => 'main@example.com',
            'password' => 'password123',
            'role' => UserRole::Salesperson->value,
            'branch_id' => $main->id,
        ])
            ->assertCreated()
            ->assertJsonPath('branch_id', $main->id);

        $this->assertDatabaseHas('users', [
            'email' => 'main@example.com',
            'branch_id' => $main->id,
        ]);

        $salesUser = User::query()->where('email', 'main@example.com')->firstOrFail();

        $this->actingAs($salesUser, 'api')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'main@example.com')
            ->assertJsonPath('branch_id', $main->id)
            ->assertJsonPath('can_select_branch', false);

        $this->actingAs($salesUser, 'api')->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branchB->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $me = $this->actingAs($admin, 'api')->getJson('/api/v1/auth/me')->assertOk()->json();
        $this->assertTrue($me['can_select_branch']);
        $this->assertTrue($me['can_access_all_branches']);
        $this->assertCount(2, $me['accessible_branch_ids']);
    }
}
