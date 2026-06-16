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

class DashboardSupplierPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

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
    }

    public function test_purchase_and_installment_payment_reflect_on_dashboard(): void
    {
        $branch = Branch::query()->firstOrFail();
        $this->withToken($this->token)->putJson('/api/v1/settings/capital', [
            'capital_amount' => 500000,
            'reason' => 'Opening capital for cash snapshot test',
            'branch_id' => $branch->id,
        ])->assertOk();

        $cashBefore = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/cash?branch_id='.$branch->id)
            ->assertOk()
            ->json();

        $supplier = Supplier::query()->create([
            'name' => 'Dash Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);
        $part = Part::query()->create([
            'code' => 'PO-DASH',
            'name' => 'PO Dash Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 30,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $poId = (string) $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'installments',
            'installment_count' => 4,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $part->id, 'quantity' => 10, 'unit_cost' => 10000],
            ],
        ])->assertCreated()->json('id');

        Cache::flush();

        $afterOrder = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();

        $this->assertEquals(100000.0, $afterOrder['total_supplier_debt']);
        $this->assertEquals(100000.0, $afterOrder['weekly_purchases_ordered']);
        $this->assertEquals(100000.0, $afterOrder['unpaid_installments_total']);
        $this->assertEquals(4, $afterOrder['unpaid_installments_count']);
        $this->assertEquals(100000.0, $afterOrder['must_pay_suppliers']);
        $this->assertEquals($cashBefore['cash_on_hand_realized'], $afterOrder['cash_on_hand_realized']);

        $installmentId = (string) $this->withToken($this->token)
            ->getJson("/api/v1/purchases/{$poId}")
            ->json('installments.0.id');

        $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
        ])->assertOk();

        Cache::flush();

        $afterPay = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();

        $this->assertEquals(75000.0, $afterPay['total_supplier_debt']);
        $this->assertEquals(25000.0, $afterPay['weekly_supplier_payments']);
        $this->assertEquals(75000.0, $afterPay['unpaid_installments_total']);
        $this->assertEquals(75000.0, $afterPay['must_pay_suppliers']);
        $this->assertEquals(25000.0, $afterPay['weekly_cash_out_realized']);
        $this->assertEquals(
            (float) $cashBefore['cash_on_hand_realized'] - 25000.0,
            (float) $afterPay['cash_on_hand_realized']
        );
    }
}
