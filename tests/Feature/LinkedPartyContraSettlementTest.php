<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedPartyContraSettlementTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        $this->branch = Branch::query()->firstOrFail();
        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');
    }

    public function test_linked_balance_shows_net_they_owe_us(): void
    {
        [$customerId, $supplierId] = $this->createLinkedAbu(customerBalance: 500, supplierDebt: 300);

        $balance = $this->withToken($this->token)
            ->getJson("/api/v1/customers/{$customerId}/linked-balance")
            ->assertOk()
            ->json();

        $this->assertTrue($balance['is_linked']);
        $this->assertEquals(500.0, $balance['customer_balance']);
        $this->assertEquals(300.0, $balance['supplier_debt']);
        $this->assertEquals(200.0, $balance['net_amount']);
        $this->assertSame('they_owe_us', $balance['net_direction']);
        $this->assertEquals(300.0, $balance['max_offset_amount']);
    }

    public function test_offset_reduces_both_balances(): void
    {
        [$customerId, $supplierId] = $this->createLinkedAbu(customerBalance: 500, supplierDebt: 300);

        $this->withToken($this->token)
            ->postJson("/api/v1/customers/{$customerId}/offset-supplier", [
                'amount' => 300,
                'notes' => 'Net part of Abu balance',
            ])
            ->assertCreated()
            ->assertJsonPath('amount', 300);

        $this->withToken($this->token)
            ->getJson("/api/v1/customers/{$customerId}/balance")
            ->assertOk()
            ->assertJsonPath('outstanding_balance', 200);

        $this->withToken($this->token)
            ->getJson("/api/v1/suppliers/{$supplierId}/debt")
            ->assertOk();

        $supplier = Supplier::query()->findOrFail($supplierId);
        $this->assertEquals('0.00', $supplier->total_debt);

        $after = $this->withToken($this->token)
            ->getJson("/api/v1/customers/{$customerId}/linked-balance")
            ->assertOk()
            ->json();

        $this->assertEquals(200.0, $after['net_amount']);
        $this->assertSame('they_owe_us', $after['net_direction']);
        $this->assertEquals(0.0, $after['max_offset_amount']);
    }

    public function test_default_offset_uses_max_allowed(): void
    {
        [$customerId] = $this->createLinkedAbu(customerBalance: 400, supplierDebt: 250);

        $this->withToken($this->token)
            ->postJson("/api/v1/customers/{$customerId}/offset-supplier", [])
            ->assertCreated()
            ->assertJsonPath('amount', 250);
    }

    public function test_supplier_linked_balance_matches_customer_view(): void
    {
        [$customerId, $supplierId] = $this->createLinkedAbu(customerBalance: 100, supplierDebt: 400);

        $fromSupplier = $this->withToken($this->token)
            ->getJson("/api/v1/suppliers/{$supplierId}/linked-balance")
            ->assertOk()
            ->json();

        $this->assertSame('we_owe_them', $fromSupplier['net_direction']);
        $this->assertEquals(300.0, $fromSupplier['net_amount']);
        $this->assertEquals(100.0, $fromSupplier['max_offset_amount']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createLinkedAbu(float $customerBalance, float $supplierDebt): array
    {
        $part = Part::query()->create([
            'code' => 'ABU-'.uniqid(),
            'name' => 'Shared Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => $customerBalance,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $this->branch->id, 'quantity' => 100]);

        $supplier = Supplier::query()->create([
            'name' => 'Abu Supplier',
            'contact_person' => 'Abu',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Abu Customer',
            'type' => 'credit',
            'phone' => null,
            'address' => null,
            'credit_limit' => 10000,
            'outstanding_balance' => 0,
            'linked_supplier_id' => $supplier->id,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        if ($customerBalance > 0) {
            $this->withToken($this->token)->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'branch_id' => $this->branch->id,
                'payment_type' => 'credit',
                'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_price' => $customerBalance]],
            ])->assertCreated();
        }

        if ($supplierDebt > 0) {
            $this->withToken($this->token)->postJson('/api/v1/purchases', [
                'supplier_id' => $supplier->id,
                'branch_id' => $this->branch->id,
                'payment_type' => 'immediate',
                'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => $supplierDebt]],
            ])->assertCreated();
        }

        $customer->refresh();
        $supplier->refresh();

        return [$customer->id, $supplier->id];
    }
}
