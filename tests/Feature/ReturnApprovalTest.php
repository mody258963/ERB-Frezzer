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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReturnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $this->token = (string) $login->json('token');
    }

    public function test_customer_return_refund_cash_restocks_inventory(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = $this->makePart('RET-CASH');
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 10]);
        $customer = $this->makeCustomer('Return Customer');

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 2]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 2, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $this->assertSame(10, (int) Stock::query()->where('part_id', $part->id)->where('branch_id', $branch->id)->value('quantity'));
    }

    public function test_defective_writeoff_refunds_money_without_restock(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = $this->makePart('RET-DEF');
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 6]);
        $customer = $this->makeCustomer('Defect Customer');

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 1]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 120, 'condition' => 'defective'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'writeoff',
        ])->assertOk();

        $this->assertSame(5, (int) Stock::query()->where('part_id', $part->id)->where('branch_id', $branch->id)->value('quantity'));
    }

    public function test_customer_refund_subtracts_from_dashboard(): void
    {
        Cache::flush();
        $branch = Branch::query()->firstOrFail();
        $part = $this->makePart('RET-DASH');
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 50]);
        $customer = $this->makeCustomer('Dash Customer');

        $invoiceId = (string) $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 200]],
        ])->json('id');

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 200, 'condition' => 'sellable'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $summary = $this->withToken($this->token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertEquals(200.0, $summary['weekly_customer_refunds']);
        $this->assertEquals(0.0, $summary['weekly_net_sales']);
    }

    public function test_supplier_return_supplier_credit_reduces_stock(): void
    {
        $branch = Branch::query()->firstOrFail();
        $part = $this->makePart('RET-SUP');
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 10]);
        $supplier = Supplier::query()->create([
            'name' => 'Sup',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $poId = (string) $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'immediate',
            'items' => [['part_id' => $part->id, 'quantity' => 5, 'unit_cost' => 100]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/purchases/{$poId}/receive")->assertOk();

        $returnId = (string) $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'supplier_return',
            'reference_id' => $poId,
            'reference_type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 3, 'unit_price' => 100, 'condition' => 'defective'],
            ],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'supplier_credit',
        ])->assertOk();

        $this->assertSame(12, (int) Stock::query()->where('part_id', $part->id)->where('branch_id', $branch->id)->value('quantity'));
        $this->assertEquals(200.0, (float) $supplier->fresh()->total_debt);
    }

    private function makeCustomer(string $name): Customer
    {
        return Customer::query()->create([
            'name' => $name,
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);
    }

    private function makePart(string $code): Part
    {
        $this->seed(PartCategorySeeder::class);

        return Part::query()->create([
            'code' => $code,
            'name' => $code,
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 25,
            'min_stock' => 0,
            'is_active' => true,
        ]);
    }
}
