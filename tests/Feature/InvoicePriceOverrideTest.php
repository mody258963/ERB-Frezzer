<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePriceOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_uses_catalog_price_when_unit_price_omitted(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();
        $part = $this->createPart('CAT-001', sellPrice: 100);
        $this->stock($part, $branch->id, 10);
        $customer = $this->createCustomer();
        $user = $this->adminToken();

        $response = $this->withToken($user)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('subtotal', 200);
        $response->assertJsonPath('total', 200);

        $item = InvoiceItem::query()->where('invoice_id', $response->json('id'))->first();
        $this->assertNotNull($item);
        $this->assertEquals(100, (float) $item->unit_price);
        $this->assertEquals(200, (float) $item->total);
        $this->assertEquals(100, (float) $part->fresh()->sell_price);
    }

    public function test_invoice_accepts_custom_unit_price_per_line(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();
        $part = $this->createPart('OVR-001', sellPrice: 100);
        $this->stock($part, $branch->id, 10);
        $customer = $this->createCustomer();
        $user = $this->adminToken();

        $response = $this->withToken($user)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 2, 'unit_price' => 175.5],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('subtotal', 351);
        $response->assertJsonPath('total', 351);

        $item = InvoiceItem::query()->where('invoice_id', $response->json('id'))->first();
        $this->assertNotNull($item);
        $this->assertEquals(175.5, (float) $item->unit_price);
        $this->assertEquals(351, (float) $item->total);
        $this->assertEquals(100, (float) $part->fresh()->sell_price);
    }

    public function test_invoice_rejects_negative_unit_price(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();
        $part = $this->createPart('NEG-001', sellPrice: 50);
        $this->stock($part, $branch->id, 5);
        $customer = $this->createCustomer();
        $user = $this->adminToken();

        $this->withToken($user)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => -10],
            ],
        ])->assertStatus(422);
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        return (string) $login->json('token');
    }

    private function createPart(string $code, float $sellPrice): Part
    {
        $response = $this->withToken($this->adminToken())->postJson('/api/v1/parts', [
            'code' => $code,
            'name' => 'Test '.$code,
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => $sellPrice,
            'cost_price' => 10,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $response->assertCreated();

        return Part::query()->findOrFail($response->json('id'));
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Cash Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);
    }

    private function stock(Part $part, string $branchId, int $qty): void
    {
        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branchId,
            'quantity' => $qty,
        ]);
    }
}
