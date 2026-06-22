<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardPayablesBySupplierTest extends TestCase
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

    public function test_payables_by_supplier_groups_debt_per_supplier(): void
    {
        $supplierA = $this->createSupplierWithPurchase('Supplier A', 10000.0);
        $supplierB = $this->createSupplierWithPurchase('Supplier B', 5000.0);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/payables/by-supplier')
            ->assertOk()
            ->json();

        $this->assertCount(2, $response['suppliers']);
        $this->assertEquals(15000.0, $response['total_supplier_debt']);

        $names = array_column(array_column($response['suppliers'], 'supplier'), 'name');
        $this->assertContains('Supplier A', $names);
        $this->assertContains('Supplier B', $names);

        $summary = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();

        $this->assertEquals(15000.0, $summary['must_pay_suppliers']);
    }

    private function createSupplierWithPurchase(string $name, float $totalCost): Supplier
    {
        $supplier = Supplier::query()->create([
            'name' => $name,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $part = Part::query()->create([
            'code' => 'PAY-'.uniqid(),
            'name' => 'Payable Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 30,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'installments',
            'installment_count' => 2,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => $totalCost],
            ],
        ])->assertCreated();

        return $supplier->fresh();
    }
}
