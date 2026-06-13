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

class FractionalQuantityInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_accepts_fractional_meter_quantities(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();
        $part = $this->createMeterPart('MTR-001', sellPrice: 100, initialQuantity: 10);
        $customer = $this->createCustomer();
        $user = $this->adminToken();

        $response = $this->withToken($user)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 0.5],
                ['part_id' => $part->id, 'quantity' => 0.25],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('subtotal', 75);
        $response->assertJsonPath('total', 75);

        $items = InvoiceItem::query()->where('invoice_id', $response->json('id'))->get();
        $this->assertCount(2, $items);
        $this->assertEquals(0.5, (float) $items[0]->quantity);
        $this->assertEquals(0.25, (float) $items[1]->quantity);
        $this->assertEquals(9.25, (float) Stock::query()->where('part_id', $part->id)->where('branch_id', $branch->id)->value('quantity'));
    }

    public function test_invoice_rejects_fractional_quantity_for_piece_unit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();
        $part = $this->createPiecePart('PC-001', sellPrice: 50, initialQuantity: 10);
        $customer = $this->createCustomer();
        $user = $this->adminToken();

        $this->withToken($user)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 0.5],
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

    private function createMeterPart(string $code, float $sellPrice, float $initialQuantity = 0): Part
    {
        $branch = Branch::query()->firstOrFail();
        $response = $this->withToken($this->adminToken())->postJson('/api/v1/parts?branch_id='.$branch->id, [
            'code' => $code,
            'name' => 'Test '.$code,
            'category_key' => 'compressor',
            'unit' => 'm',
            'sell_price' => $sellPrice,
            'cost_price' => 10,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
            'initial_quantity' => $initialQuantity,
        ]);
        $response->assertCreated();

        return Part::query()->findOrFail($response->json('id'));
    }

    private function createPiecePart(string $code, float $sellPrice, float $initialQuantity = 0): Part
    {
        $branch = Branch::query()->firstOrFail();
        $response = $this->withToken($this->adminToken())->postJson('/api/v1/parts?branch_id='.$branch->id, [
            'code' => $code,
            'name' => 'Test '.$code,
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => $sellPrice,
            'cost_price' => 10,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
            'initial_quantity' => $initialQuantity,
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
}
