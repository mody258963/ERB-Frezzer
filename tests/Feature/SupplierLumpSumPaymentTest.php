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

class SupplierLumpSumPaymentTest extends TestCase
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

    public function test_supplier_lump_sum_payment_allocates_fifo_across_installments(): void
    {
        $supplier = $this->createSupplierWithTwoInstallments(15000.0, 15000.0);

        $response = $this->withToken($this->token)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 30000,
            'notes' => 'Pay supplier total',
        ])->assertCreated()->json();

        $this->assertEquals(30000.0, $response['amount']);
        $this->assertEquals(0.0, $response['total_debt_after']);
        $this->assertCount(2, $response['allocations']);
        $this->assertEquals(15000.0, $response['allocations'][0]['amount']);
        $this->assertEquals(15000.0, $response['allocations'][1]['amount']);

        $supplier->refresh();
        $this->assertEquals(0.0, (float) $supplier->total_debt);
    }

    public function test_supplier_lump_sum_payment_updates_dashboard_cash_out(): void
    {
        $this->withToken($this->token)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 500000,
            'reason' => 'Opening capital',
            'branch_id' => $this->branch->id,
        ])->assertOk();

        $supplier = $this->createSupplierWithTwoInstallments(10000.0, 10000.0);
        Cache::flush();

        $before = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/cash?period=day')
            ->assertOk()
            ->json();

        $this->withToken($this->token)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 10000,
        ])->assertCreated();

        Cache::flush();

        $after = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/cash?period=day')
            ->assertOk()
            ->json();

        $this->assertEquals(
            (float) $before['period_cash_out_realized'] + 10000.0,
            (float) $after['period_cash_out_realized']
        );
    }

    public function test_supplier_payment_exceeding_debt_returns_422(): void
    {
        $supplier = $this->createSupplierWithTwoInstallments(5000.0, 5000.0);

        $this->withToken($this->token)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 15000,
        ])->assertStatus(422);
    }

    public function test_supplier_payment_history_lists_allocations(): void
    {
        $supplier = $this->createSupplierWithTwoInstallments(5000.0, 5000.0);

        $this->withToken($this->token)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 7500,
        ])->assertCreated();

        $history = $this->withToken($this->token)
            ->getJson("/api/v1/suppliers/{$supplier->id}/payments")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $amounts = array_column($history, 'amount');
        sort($amounts);
        $this->assertEquals([2500.0, 5000.0], $amounts);
    }

    private function createSupplierWithTwoInstallments(float $first, float $second): Supplier
    {
        $supplier = Supplier::query()->create([
            'name' => 'Lump Sum Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $part = Part::query()->create([
            'code' => 'LUMP-'.uniqid(),
            'name' => 'Lump Part',
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
                ['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => $first + $second],
            ],
        ])->assertCreated();

        return $supplier->fresh();
    }
}
