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

/**
 * End-to-end business flow: capital, supplier PO + installments, sales (cash/credit),
 * settlement, customer return — with dashboard assertions at each step.
 */
class DashboardFullBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Branch $branch;

    private Part $part;

    private Supplier $supplier;

    private Customer $cashCustomer;

    private Customer $creditCustomer;

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
        $categoryId = PartCategory::query()->where('key', 'compressor')->value('id');

        $this->part = Part::query()->create([
            'code' => 'FLOW-1',
            'name' => 'Flow Test Part',
            'category_id' => $categoryId,
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create([
            'part_id' => $this->part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 20,
        ]);

        $this->supplier = Supplier::query()->create([
            'name' => 'Flow Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $this->cashCustomer = Customer::query()->create([
            'name' => 'Cash Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->creditCustomer = Customer::query()->create([
            'name' => 'Credit Customer',
            'type' => 'credit',
            'phone' => null,
            'address' => null,
            'credit_limit' => 50000,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_full_business_flow_dashboard_numbers_at_each_step(): void
    {
        $auth = fn () => $this->withToken($this->token);

        // 1) Admin sets business capital
        $auth()->putJson('/api/v1/settings/capital', [
            'capital_amount' => 500000,
            'reason' => 'Opening capital',
        ])->assertOk();

        $this->assertDashboard([
            'business_capital' => 500000.0,
            'total_supplier_debt' => 0.0,
            'total_receivables' => 0.0,
        ]);

        // 2) Purchase 100,000 EGP on 4 installments (before receive)
        $poId = (string) $auth()->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'installments',
            'installment_count' => 4,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 10, 'unit_cost' => 10000],
            ],
        ])->assertCreated()->json('id');

        $this->assertDashboard([
            'business_capital' => 500000.0,
            'total_supplier_debt' => 100000.0,
            'weekly_purchases_ordered' => 100000.0,
            'weekly_purchases_received' => 0.0,
            'unpaid_installments_total' => 100000.0,
            'unpaid_installments_count' => 4,
            'weekly_supplier_payments' => 0.0,
        ]);

        // 3) Receive goods into stock
        $auth()->patchJson("/api/v1/purchases/{$poId}/receive", [
            'branch_id' => $this->branch->id,
        ])->assertOk();

        // Stock: 20 + 10 = 30 units × cost 100 = 3000
        $this->assertDashboard([
            'weekly_purchases_received' => 100000.0,
            'total_stock_value_cost' => 3000.0,
        ]);

        // 4) Pay first supplier installment (25,000)
        $installmentId = (string) $auth()->getJson("/api/v1/purchases/{$poId}")
            ->json('installments.0.id');

        $auth()->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertDashboard([
            'total_supplier_debt' => 75000.0,
            'weekly_supplier_payments' => 25000.0,
            'unpaid_installments_total' => 75000.0,
            'unpaid_installments_count' => 3,
        ]);

        // 5) Cash sale: 2 × 200 = 400 subtotal, profit (200-100)×2 = 200
        $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->cashCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $this->part->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertDashboard([
            'weekly_revenue' => 400.0,
            'weekly_net_sales' => 400.0,
            'weekly_gross_profit' => 200.0,
            'weekly_profit' => 200.0,
            'weekly_customer_refunds' => 0.0,
        ]);

        // 6) Credit sale: 1 × 200 = 200 (money owed by customer)
        $creditInvoiceId = (string) $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $this->part->id, 'quantity' => 1]],
        ])->assertCreated()->json('id');

        $this->assertDashboard([
            'weekly_revenue' => 600.0,
            'weekly_gross_profit' => 300.0,
            'weekly_profit' => 300.0,
            'total_receivables' => 200.0,
        ]);

        // 7) Saturday settlement — collect credit from customer
        $auth()->postJson('/api/v1/settlements', [
            'customer_id' => $this->creditCustomer->id,
            'settlement_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->assertDashboard([
            'total_receivables' => 0.0,
            'weekly_revenue' => 600.0,
        ]);

        // 8) Customer return on cash invoice path — return 1 unit from credit invoice with refund
        $returnId = (string) $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $creditInvoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'part_id' => $this->part->id,
                    'quantity' => 1,
                    'unit_price' => 200,
                    'condition' => 'sellable',
                ],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $this->assertDashboard([
            'weekly_revenue' => 600.0,
            'weekly_customer_refunds' => 200.0,
            'weekly_net_sales' => 400.0,
            'weekly_gross_profit' => 300.0,
            'weekly_profit' => 200.0,
            'total_receivables' => 0.0,
        ]);

        // 9) Financial report matches same period totals
        $from = now()->startOfWeek()->toDateString();
        $to = now()->endOfWeek()->toDateString();
        $report = $auth()->getJson("/api/v1/reports/financial?from={$from}&to={$to}")
            ->assertOk()
            ->json();

        $this->assertEquals(600.0, $report['totals']['revenue']);
        $this->assertEquals(200.0, $report['totals']['customer_refunds']);
        $this->assertEquals(200.0, $report['totals']['profit']);
        $this->assertEquals(25000.0, $report['suppliers']['payments_in_period']);
        $this->assertEquals(100000.0, $report['suppliers']['purchases_ordered_in_period']);
        $this->assertEquals(500000.0, $report['capital']['capital_amount']);
    }

    /**
     * @param  array<string, float|int>  $expected
     */
    private function assertDashboard(array $expected): void
    {
        Cache::flush();

        $summary = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();

        foreach ($expected as $key => $value) {
            $this->assertEquals(
                $value,
                $summary[$key],
                "Dashboard field [{$key}] mismatch after transaction step."
            );
        }
    }
}
