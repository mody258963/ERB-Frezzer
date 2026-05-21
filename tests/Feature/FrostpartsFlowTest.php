<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrostpartsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_fails_when_stock_insufficient(): void
    {
        $branch = Branch::query()->create([
            'name' => 'B1',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $part = Part::query()->create([
            'code' => 'P001',
            'name' => 'Part 1',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 1,
        ]);

        $customer = Customer::query()->create([
            'name' => 'C1',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('t')->accessToken)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('failures'));
    }

    public function test_stock_transfer_completes_and_moves_quantity(): void
    {
        $b1 = Branch::query()->create(['name' => 'B1', 'address' => null, 'phone' => null, 'is_active' => true]);
        $b2 = Branch::query()->create(['name' => 'B2', 'address' => null, 'phone' => null, 'is_active' => true]);

        $part = Part::query()->create([
            'code' => 'P-T',
            'name' => 'T',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $b1->id, 'quantity' => 10]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $b2->id, 'quantity' => 0]);

        $user = User::factory()->create();

        $create = $this->withToken($user->createToken('t')->accessToken)->postJson('/api/v1/transfers', [
            'from_branch_id' => $b1->id,
            'to_branch_id' => $b2->id,
            'items' => [
                ['part_id' => $part->id, 'quantity' => 3],
            ],
        ]);

        $create->assertCreated();
        $id = $create->json('id');

        $this->withToken($user->createToken('t2')->accessToken)
            ->patchJson('/api/v1/transfers/'.$id.'/complete')
            ->assertOk();

        $this->assertSame(7, (int) Stock::query()->where('part_id', $part->id)->where('branch_id', $b1->id)->value('quantity'));
        $this->assertSame(3, (int) Stock::query()->where('part_id', $part->id)->where('branch_id', $b2->id)->value('quantity'));
    }

    public function test_installment_payment_reduces_supplier_debt(): void
    {
        $branch = Branch::query()->create(['name' => 'B', 'address' => null, 'phone' => null, 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'name' => 'S',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $part = Part::query()->create([
            'code' => 'PX',
            'name' => 'PX',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $poResp = $this->withToken($user->createToken('t')->accessToken)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'immediate',
            'items' => [
                ['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => 100],
            ],
        ]);

        $poResp->assertCreated();
        $supplier->refresh();
        $this->assertEquals(100, (float) $supplier->total_debt);

        $instId = $poResp->json('installments.0.id');

        $this->withToken($user->createToken('t2')->accessToken)->postJson('/api/v1/installments/'.$instId.'/pay', [
            'payment_method' => 'cash',
        ])->assertOk();

        $supplier->refresh();
        $this->assertEquals(0, (float) $supplier->total_debt);
    }
}
