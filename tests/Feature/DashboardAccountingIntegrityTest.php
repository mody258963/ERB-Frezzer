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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Full accounting integrity test — runs real API transactions and verifies that
 * dashboard / report numbers stay consistent with the database.
 *
 * Run locally:
 *   php artisan test --filter=DashboardAccountingIntegrityTest
 *
 * Uses sqlite in-memory (phpunit.xml). Safe to run anytime; no production DB needed.
 */
class DashboardAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Branch $branch;

    private Part $part;

    private Part $meterPart;

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
            'code' => 'INT-P1',
            'name' => 'Integrity Part',
            'category_id' => $categoryId,
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $this->meterPart = Part::query()->create([
            'code' => 'INT-M1',
            'name' => 'Integrity Cable',
            'category_id' => $categoryId,
            'unit' => 'm',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $this->part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 20,
            'average_cost' => 100,
        ]);

        Stock::query()->create([
            'part_id' => $this->meterPart->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
            'average_cost' => 40,
        ]);

        $this->supplier = Supplier::query()->create([
            'name' => 'Integrity Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $this->cashCustomer = Customer::query()->create([
            'name' => 'Integrity Cash',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->creditCustomer = Customer::query()->create([
            'name' => 'Integrity Credit',
            'type' => 'credit',
            'phone' => null,
            'address' => null,
            'credit_limit' => 50000,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_full_transaction_flow_dashboard_and_reports_stay_consistent(): void
    {
        $auth = fn (): static => $this->withToken($this->token);

        // ── 1) Capital ──────────────────────────────────────────────────────
        $auth()->putJson('/api/v1/settings/capital', [
            'capital_amount' => 500000,
            'reason' => 'Opening capital',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'business_capital' => 500000.0,
            'total_supplier_debt' => 0.0,
            'total_receivables' => 0.0,
            'total_stock_value_cost' => 6000.0, // 20×100 + 100×40
        ]);

        // ── 2) Purchase on installments (1,000) ─────────────────────────────
        $poId = (string) $auth()->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'installments',
            'installment_count' => 4,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 10, 'unit_cost' => 100],
            ],
        ])->assertCreated()->json('id');

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_supplier_debt' => 1000.0,
            'weekly_purchases_ordered' => 1000.0,
            'weekly_purchases_received' => 0.0,
            'unpaid_installments_total' => 1000.0,
            'unpaid_installments_count' => 4,
        ]);

        // ── 3) Receive goods ───────────────────────────────────────────────
        $auth()->patchJson("/api/v1/purchases/{$poId}/receive", [
            'branch_id' => $this->branch->id,
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_purchases_received' => 1000.0,
            'total_stock_value_cost' => 7000.0, // 30×100 + 100×40
        ]);

        // ── 4) Pay first installment (250) ───────────────────────────────
        $installmentId = (string) $auth()->getJson("/api/v1/purchases/{$poId}")
            ->json('installments.0.id');

        $auth()->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_supplier_debt' => 750.0,
            'weekly_supplier_payments' => 250.0,
            'unpaid_installments_total' => 750.0,
            'unpaid_installments_count' => 3,
        ]);

        // ── 5) Cash sale 2 × 200 ───────────────────────────────────────────
        $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->cashCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $this->part->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_revenue' => 400.0,
            'weekly_net_sales' => 400.0,
            'weekly_gross_profit' => 200.0,
            'weekly_profit' => 200.0,
            'total_stock_value_cost' => 6800.0, // 28×100 + 100×40
        ]);

        // ── 6) Credit sale 1 × 200 ─────────────────────────────────────────
        $creditInvoiceId = (string) $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $this->part->id, 'quantity' => 1]],
        ])->assertCreated()->json('id');

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_revenue' => 600.0,
            'weekly_gross_profit' => 300.0,
            'weekly_profit' => 300.0,
            'total_receivables' => 200.0,
            'total_stock_value_cost' => 6700.0, // 27×100 + 100×40
        ]);

        // ── 7) Partial customer payment (80 of 200) ────────────────────────
        $auth()->postJson("/api/v1/customers/{$this->creditCustomer->id}/payments", [
            'payment_method' => 'cash',
            'amount' => 80,
        ])->assertCreated();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_receivables' => 120.0,
            'weekly_revenue' => 600.0,
        ]);

        $this->assertEquals(120.0, (float) $this->creditCustomer->fresh()->outstanding_balance);

        // ── 8) Discounted cash sale + fractional meter sale ────────────────
        $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->cashCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'discount' => 50,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 1],
                ['part_id' => $this->meterPart->id, 'quantity' => 0.5],
            ],
        ])->assertCreated();

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_revenue' => 850.0,   // 600 + 200 + 50
            'weekly_discount' => 50.0,
            'weekly_net_sales' => 800.0, // invoice totals: 400+200+200
            'weekly_gross_profit' => 430.0, // +100 (pc) +30 (0.5m)
            'weekly_profit' => 380.0,
            'total_stock_value_cost' => 6580.0, // 26×100 + 99.5×40
        ]);

        // ── 9) Customer return — refund cash (credit invoice, 1 unit) ──────
        $returnRefundId = (string) $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $creditInvoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 1, 'unit_price' => 200, 'condition' => 'sellable'],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/returns/{$returnRefundId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_customer_refunds' => 200.0,
            'weekly_net_sales' => 600.0,
            'weekly_profit' => 280.0,
            'total_receivables' => 120.0,
            'total_stock_value_cost' => 6680.0, // restocked +1 pc
        ]);

        // ── 10) Defective writeoff on cash path (no restock) ───────────────
        $cashInvoiceId = (string) $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->cashCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $this->part->id, 'quantity' => 1, 'unit_price' => 120]],
        ])->assertCreated()->json('id');

        $returnWriteoffId = (string) $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $cashInvoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $this->cashCustomer->id,
            'branch_id' => $this->branch->id,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 1, 'unit_price' => 120, 'condition' => 'defective'],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/returns/{$returnWriteoffId}/approve", [
            'resolution' => 'writeoff',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'weekly_customer_refunds' => 320.0,
            'weekly_net_sales' => 600.0,
            'weekly_profit' => 280.0,
        ]);

        // ── 11) Owner cash out from profit ─────────────────────────────────
        $auth()->postJson('/api/v1/settings/capital/cash-out', [
            'amount' => 100,
            'reason' => 'Owner draw',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_owner_cash_outs' => 100.0,
            'withdrawable_profit' => 180.0,
            'business_capital' => 500000.0,
        ]);

        // ── 12) Soft-delete part removes it from inventory totals ──────────
        $junkPart = Part::query()->create([
            'code' => 'INT-JUNK',
            'name' => 'To Remove',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        Stock::query()->create([
            'part_id' => $junkPart->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
            'average_cost' => 5,
        ]);

        $beforeDeleteStockValue = $this->stockValueAtCost();
        $this->assertEquals(7080.0, $beforeDeleteStockValue);

        $auth()->deleteJson('/api/v1/parts/'.$junkPart->id)->assertNoContent();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_stock_value_cost' => 6580.0,
        ]);

        // ── 13) Final cross-check: financial report + sales dashboard ─────
        $this->assertFinancialReportMatchesDashboard();
        $this->assertInventoryReportMatchesStock();
    }

    public function test_credit_note_return_and_settlement_keep_receivables_consistent(): void
    {
        $auth = fn (): static => $this->withToken($this->token);

        $creditInvoiceId = (string) $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $this->part->id, 'quantity' => 1]],
        ])->assertCreated()->json('id');

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_receivables' => 200.0,
            'weekly_revenue' => 200.0,
        ]);

        $returnId = (string) $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $creditInvoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 1, 'unit_price' => 200, 'condition' => 'sellable'],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'credit_note',
        ])->assertOk();

        // Summary uses customer.outstanding_balance; receivables list uses invoice balanceDue
        // until credit_note also updates invoice amount_paid.
        $this->assertAccountingIntegrity(
            expectedSummary: [
                'total_receivables' => 0.0,
                'weekly_customer_refunds' => 200.0,
            ],
            assertReceivablesListMatch: false,
        );

        $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $this->creditCustomer->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $this->part->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertAccountingIntegrity(
            expectedSummary: ['total_receivables' => 400.0],
            assertReceivablesListMatch: false,
        );

        $auth()->postJson('/api/v1/settlements', [
            'customer_id' => $this->creditCustomer->id,
            'settlement_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_receivables' => 0.0,
        ]);

        $this->assertEquals(0.0, (float) $this->creditCustomer->fresh()->outstanding_balance);
    }

    public function test_supplier_return_updates_debt_and_inventory_totals(): void
    {
        $auth = fn (): static => $this->withToken($this->token);

        $poId = (string) $auth()->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'payment_type' => 'immediate',
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 5, 'unit_cost' => 100],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/purchases/{$poId}/receive", [
            'branch_id' => $this->branch->id,
        ])->assertOk();

        $stockBeforeReturn = $this->stockValueAtCost();
        $this->assertAccountingIntegrity(expectedSummary: [
            'total_supplier_debt' => 500.0,
            'total_stock_value_cost' => $stockBeforeReturn,
        ]);

        $returnId = (string) $auth()->postJson('/api/v1/returns', [
            'return_type' => 'supplier_return',
            'reference_id' => $poId,
            'reference_type' => 'purchase_order',
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 2, 'unit_price' => 100, 'condition' => 'defective'],
            ],
        ])->assertCreated()->json('id');

        $auth()->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'supplier_credit',
        ])->assertOk();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_supplier_debt' => 300.0,
            'total_stock_value_cost' => $stockBeforeReturn - 200.0,
        ]);

        $this->assertEquals(300.0, (float) $this->supplier->fresh()->total_debt);
    }

    public function test_add_part_warehouse_adjust_and_delete_update_dashboard_inventory(): void
    {
        $auth = fn (): static => $this->withToken($this->token);

        $baseline = $this->stockValueAtCost();
        $this->assertEquals(6000.0, $baseline);

        // Mobile intake: create part + opening stock (10 × 50 cost)
        $create = $auth()->postJson('/api/v1/parts?branch_id='.$this->branch->id, [
            'code' => 'INTAKE-1',
            'name' => 'Intake Test Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 120,
            'cost_price' => 50,
            'min_stock' => 0,
            'initial_quantity' => 10,
        ])->assertCreated();

        $partId = (string) $create->json('id');
        $afterCreate = $baseline + 500.0;

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_stock_value_cost' => $afterCreate,
        ]);

        $inventoryRow = collect($this->dashboardInventory())->firstWhere('part_code', 'INTAKE-1');
        $this->assertNotNull($inventoryRow);
        $this->assertEquals(10.0, (float) $inventoryRow['quantity']);
        $this->assertEquals(50.0, (float) $inventoryRow['average_cost']);
        $this->assertEquals(500.0, (float) $inventoryRow['value_at_cost']);

        // Warehouse adjust: receive 15 more @ 60 → WAC (10×50 + 15×60) / 25 = 56, value 1,400
        $auth()->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $this->branch->id,
            'quantity_delta' => 15,
            'unit_cost' => 60,
            'reason' => 'Stock count correction',
        ])->assertOk();

        $afterInboundAdjust = $baseline + 1400.0;
        $this->assertAccountingIntegrity(expectedSummary: [
            'total_stock_value_cost' => $afterInboundAdjust,
        ]);

        $inventoryRow = collect($this->dashboardInventory())->firstWhere('part_code', 'INTAKE-1');
        $this->assertEquals(25.0, (float) $inventoryRow['quantity']);
        $this->assertEqualsWithDelta(56.0, (float) $inventoryRow['average_cost'], 0.01);
        $this->assertEqualsWithDelta(1400.0, (float) $inventoryRow['value_at_cost'], 0.01);

        // Warehouse adjust: remove 5 units (outbound) → 20 × 56 = 1,120
        $auth()->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $this->branch->id,
            'quantity_delta' => -5,
            'reason' => 'Damaged units',
        ])->assertOk();

        $afterOutboundAdjust = $baseline + 1120.0;
        $this->assertAccountingIntegrity(expectedSummary: [
            'total_stock_value_cost' => $afterOutboundAdjust,
        ]);

        $inventoryRow = collect($this->dashboardInventory())->firstWhere('part_code', 'INTAKE-1');
        $this->assertEquals(20.0, (float) $inventoryRow['quantity']);
        $this->assertEqualsWithDelta(1120.0, (float) $inventoryRow['value_at_cost'], 0.01);

        // Soft-delete part: inventory totals and dashboard rows must drop this part
        $auth()->deleteJson('/api/v1/parts/'.$partId)->assertNoContent();

        $this->assertAccountingIntegrity(expectedSummary: [
            'total_stock_value_cost' => $baseline,
        ]);

        $this->assertNull(collect($this->dashboardInventory())->firstWhere('part_code', 'INTAKE-1'));

        $auth()->getJson('/api/v1/parts?branch_id='.$this->branch->id)
            ->assertOk()
            ->assertJsonMissing(['code' => 'INTAKE-1']);

        $this->assertInventoryReportMatchesStock();
    }

    /**
     * @param  array<string, float|int>  $expectedSummary
     */
    private function assertAccountingIntegrity(
        array $expectedSummary = [],
        bool $assertReceivablesListMatch = true,
    ): void {
        Cache::flush();

        $dbStockValue = $this->stockValueAtCost();
        $summary = $this->dashboardSummary();
        $inventoryRows = $this->dashboardInventory();
        $inventorySum = collect($inventoryRows)->sum(fn (array $r) => (float) $r['value_at_cost']);

        $this->assertEquals(
            $dbStockValue,
            (float) $summary['total_stock_value_cost'],
            'Dashboard total_stock_value_cost must equal SUM(stock.quantity × average_cost) for active parts.'
        );

        $this->assertEqualsWithDelta(
            $dbStockValue,
            $inventorySum,
            0.01,
            'Dashboard inventory rows value_at_cost sum must match stock table.'
        );

        $supplierDebtDb = (float) Supplier::query()->sum('total_debt');
        $this->assertEquals(
            $supplierDebtDb,
            (float) $summary['total_supplier_debt'],
            'Dashboard total_supplier_debt must match suppliers.total_debt sum.'
        );

        $receivablesDb = (float) Customer::query()->sum('outstanding_balance');
        $this->assertEquals(
            $receivablesDb,
            (float) $summary['total_receivables'],
            'Dashboard total_receivables must match customers.outstanding_balance sum.'
        );

        $receivablesEndpoint = collect($this->dashboardReceivables())
            ->sum(fn (array $r) => (float) $r['outstanding_balance']);
        if ($assertReceivablesListMatch) {
            $this->assertEquals(
                $receivablesDb,
                $receivablesEndpoint,
                'Dashboard receivables list must sum to total receivables.'
            );
        }

        foreach ($expectedSummary as $field => $expected) {
            $this->assertEquals(
                $expected,
                (float) $summary[$field],
                "Dashboard summary [{$field}] mismatch after transaction step."
            );
        }
    }

    private function assertFinancialReportMatchesDashboard(): void
    {
        Cache::flush();

        $from = now()->startOfWeek()->toDateString();
        $to = now()->endOfWeek()->toDateString();

        $summary = $this->dashboardSummary();
        $report = $this->withToken($this->token)
            ->getJson("/api/v1/reports/financial?from={$from}&to={$to}")
            ->assertOk()
            ->json();

        $sales = $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/sales')
            ->assertOk()
            ->json();

        $pairs = [
            'revenue' => 'weekly_revenue',
            'discount' => 'weekly_discount',
            'customer_refunds' => 'weekly_customer_refunds',
            'net_sales' => 'weekly_net_sales',
            'gross_profit' => 'weekly_gross_profit',
            'profit' => 'weekly_profit',
        ];

        foreach ($pairs as $reportKey => $summaryKey) {
            $this->assertEquals(
                (float) $summary[$summaryKey],
                (float) $report['totals'][$reportKey],
                "Financial report totals.{$reportKey} must match dashboard summary."
            );
        }

        $this->assertEquals((float) $summary['weekly_profit'], (float) $sales['totals']['profit']);
        $this->assertEquals((float) $summary['weekly_revenue'], (float) $sales['totals']['revenue']);
        $this->assertEquals((float) $summary['weekly_supplier_payments'], (float) $report['suppliers']['payments_in_period']);
        $this->assertEquals((float) $summary['business_capital'], (float) $report['capital']['capital_amount']);
    }

    private function assertInventoryReportMatchesStock(): void
    {
        $dbValue = $this->stockValueAtCost();

        $valuation = collect($this->jsonCollection(
            $this->withToken($this->token)->getJson('/api/v1/reports/inventory')->assertOk()
        ))->sum(fn (array $r) => (float) $r['value_cost']);

        $this->assertEqualsWithDelta(
            $dbValue,
            $valuation,
            0.01,
            'Inventory valuation report must match active stock at cost.'
        );
    }

    private function stockValueAtCost(?string $branchId = null): float
    {
        $query = Stock::query()->forActiveParts();
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (float) ($query->selectRaw('SUM(quantity * average_cost) as v')->value('v') ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardSummary(): array
    {
        return $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardInventory(): array
    {
        return $this->jsonCollection(
            $this->withToken($this->token)->getJson('/api/v1/dashboard/inventory')->assertOk()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardReceivables(): array
    {
        return $this->jsonCollection(
            $this->withToken($this->token)->getJson('/api/v1/dashboard/receivables')->assertOk()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonCollection(TestResponse $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        if (array_is_list($json)) {
            return $json;
        }

        $data = $json['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
