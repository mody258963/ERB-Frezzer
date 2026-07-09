<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $warehouseUser;

    private User $salespersonUser;

    private User $adminUser;

    private string $warehouseToken;

    private string $salespersonToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $this->branch = Branch::query()->firstOrFail();

        $this->warehouseUser = User::factory()->create([
            'email' => 'warehouse@example.com',
            'role' => UserRole::Warehouse,
            'branch_id' => $this->branch->id,
        ]);

        $this->salespersonUser = User::factory()->create([
            'email' => 'sales@example.com',
            'role' => UserRole::Salesperson,
            'branch_id' => $this->branch->id,
        ]);

        $this->adminUser = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $warehouseLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'warehouse@example.com',
            'password' => 'password',
        ])->assertOk();
        $warehouseLogin->assertJsonPath('user.role', 'warehouse');
        $this->warehouseToken = (string) $warehouseLogin->json('token');

        $salesLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'sales@example.com',
            'password' => 'password',
        ])->assertOk();
        $salesLogin->assertJsonPath('user.role', 'salesperson');
        $this->salespersonToken = (string) $salesLogin->json('token');

        $this->assertNotSame($this->warehouseToken, $this->salespersonToken);
    }

    public function test_manager_can_access_dashboard_and_reports(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager-access@example.com',
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
        ]);

        Passport::actingAs($manager, [], 'api');
        $this->getJson('/api/v1/auth/me')->assertJsonPath('role', 'manager');
        $this->getJson('/api/v1/dashboard/summary')->assertOk();
        $this->getJson('/api/v1/reports/sales')->assertOk();
    }

    public function test_admin_can_access_all_branches_via_auth_me(): void
    {
        $branchB = Branch::query()->create([
            'name' => 'Admin Access Branch B',
            'is_active' => true,
        ]);

        Passport::actingAs($this->adminUser, [], 'api');
        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json();

        $this->assertTrue($me['can_access_all_branches']);
        $this->assertTrue($me['can_select_branch']);
        $this->assertContains($this->branch->id, $me['accessible_branch_ids']);
        $this->assertContains($branchB->id, $me['accessible_branch_ids']);
    }

    public function test_auth_me_exposes_permission_flags_for_operational_roles(): void
    {
        Passport::actingAs($this->warehouseUser, [], 'api');
        $warehouse = $this->getJson('/api/v1/auth/me')->assertOk()->json();

        Passport::actingAs($this->salespersonUser, [], 'api');
        $sales = $this->getJson('/api/v1/auth/me')->assertOk()->json();

        $this->assertFalse($warehouse['can_view_dashboard']);
        $this->assertFalse($warehouse['can_view_capital']);
        $this->assertFalse($warehouse['can_view_reports']);
        $this->assertFalse($warehouse['can_cash_out_profit']);
        $this->assertTrue($warehouse['can_pay_suppliers']);
        $this->assertTrue($warehouse['can_collect_customer_payments']);
        $this->assertTrue($warehouse['can_approve_returns']);
        $this->assertTrue($warehouse['can_create_purchases']);

        $this->assertFalse($sales['can_view_dashboard']);
        $this->assertFalse($sales['can_view_capital']);
        $this->assertFalse($sales['can_view_reports']);
        $this->assertFalse($sales['can_cash_out_profit']);
        $this->assertEquals('salesperson', $sales['role']);
        $this->assertTrue($sales['can_pay_suppliers']);
        $this->assertTrue($sales['can_collect_customer_payments']);
        $this->assertTrue($sales['can_approve_returns']);
        $this->assertFalse($sales['can_create_purchases']);
    }

    public function test_warehouse_and_salesperson_cannot_access_dashboard_or_capital(): void
    {
        foreach ([$this->warehouseUser, $this->salespersonUser] as $user) {
            Passport::actingAs($user, [], 'api');
            $this->getJson('/api/v1/dashboard/summary')->assertForbidden();
            $this->getJson('/api/v1/dashboard/cash')->assertForbidden();
            $this->getJson('/api/v1/settings/capital')->assertForbidden();
            $this->postJson('/api/v1/settings/capital/cash-out', [
                'amount' => 100,
                'reason' => 'test',
            ])->assertForbidden();
        }
    }

    public function test_warehouse_and_salesperson_cannot_access_reports(): void
    {
        $reportPaths = [
            '/api/v1/reports/financial',
            '/api/v1/reports/sales',
            '/api/v1/reports/inventory',
            '/api/v1/reports/customers',
            '/api/v1/reports/suppliers',
            '/api/v1/reports/returns',
            '/api/v1/reports/parts-sales-chart',
        ];

        foreach ([$this->warehouseToken, $this->salespersonToken] as $token) {
            foreach ($reportPaths as $path) {
                $this->withToken($token)->getJson($path)->assertForbidden();
            }
        }
    }

    public function test_warehouse_and_salesperson_can_pay_supplier(): void
    {
        $supplier = $this->createSupplierWithDebt(1000.0);

        $this->withToken($this->warehouseToken)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 200,
        ])->assertCreated();

        $this->withToken($this->salespersonToken)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 150,
        ])->assertCreated();
    }

    public function test_warehouse_and_salesperson_can_list_grouped_supplier_payables(): void
    {
        Supplier::query()->create([
            'name' => 'Grouped Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 250,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        foreach ([$this->warehouseUser, $this->salespersonUser] as $user) {
            Passport::actingAs($user, [], 'api');
            $this->getJson('/api/v1/suppliers/payables/by-supplier')
                ->assertOk()
                ->assertJsonStructure(['suppliers']);
        }
    }

    public function test_salesperson_and_warehouse_can_collect_customer_payment(): void
    {
        $adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $customerId = (string) $this->withToken($adminToken)->postJson('/api/v1/customers', [
            'name' => 'Credit Customer',
            'type' => 'credit',
            'credit_limit' => 10000,
            'branch_id' => $this->branch->id,
        ])->assertCreated()->json('id');

        $part = Part::query()->create([
            'code' => 'CUST-'.uniqid(),
            'name' => 'Customer Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 10,
            'average_cost' => 50,
        ]);

        $this->withToken($adminToken)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $this->branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $part->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->withToken($this->salespersonToken)->postJson("/api/v1/customers/{$customerId}/payments", [
            'amount' => 30,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->withToken($this->warehouseToken)->postJson("/api/v1/customers/{$customerId}/payments", [
            'amount' => 20,
            'payment_method' => 'cash',
        ])->assertCreated();
    }

    public function test_warehouse_can_create_purchase_order(): void
    {
        $adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $supplier = Supplier::query()->create([
            'name' => 'PO Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $part = Part::query()->create([
            'code' => 'PO-'.uniqid(),
            'name' => 'PO Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 80,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $this->withToken($this->warehouseToken)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'installments',
            'installment_count' => 1,
            'installment_start_date' => now()->toDateString(),
            'items' => [['part_id' => $part->id, 'quantity' => 5, 'unit_cost' => 40]],
        ])->assertCreated();
    }

    public function test_branch_user_sees_part_with_stock_even_when_branch_id_null(): void
    {
        $part = Part::query()->create([
            'code' => 'LEGACY-'.uniqid(),
            'name' => 'Legacy Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => null,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 3,
            'average_cost' => 50,
        ]);

        $ids = collect($this->withToken($this->warehouseToken)->getJson('/api/v1/parts')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($part->id, $ids);
    }

    private function createSupplierWithDebt(float $amount): Supplier
    {
        $supplier = Supplier::query()->create([
            'name' => 'Role Access Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $part = Part::query()->create([
            'code' => 'ROLE-'.uniqid(),
            'name' => 'Role Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 30,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $adminToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($adminToken)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'installments',
            'installment_count' => 1,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => $amount],
            ],
        ])->assertCreated();

        return $supplier->fresh();
    }
}
