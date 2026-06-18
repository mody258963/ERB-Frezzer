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
use Tests\Support\DashboardIntegrityReportWriter;
use Tests\TestCase;
use Throwable;

/**
 * Full multi-branch integrity test with Arabic report generation.
 *
 * Run locally:
 *   php artisan test --filter=DashboardFullSystemIntegrityTest
 *
 * On success, writes docs/dashboard-integrity-results-ar.md
 */
class DashboardFullSystemIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_PATH = __DIR__.'/../../docs/dashboard-integrity-results-ar.md';

    private static bool $reportWritten = false;

    private string $token;

    private Branch $branch;

    private Branch $branchB;

    private Part $part;

    private Supplier $supplier;

    private Customer $cashCustomer;

    private Customer $creditCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        DashboardIntegrityReportWriter::reset();
        self::$reportWritten = false;

        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        Cache::flush();

        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->branch = Branch::query()->firstOrFail();
        $this->branchB = Branch::query()->create(['name' => 'Integrity Branch B', 'is_active' => true]);

        $categoryId = PartCategory::query()->where('key', 'compressor')->value('id');

        $this->part = Part::query()->create([
            'code' => 'FULL-P1',
            'name' => 'Full Integrity Part',
            'category_id' => $categoryId,
            'unit' => 'pc',
            'sell_price' => 200,
            'cost_price' => 100,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $this->part->id,
            'branch_id' => $this->branch->id,
            'quantity' => 30,
            'average_cost' => 100,
        ]);

        $this->supplier = Supplier::query()->create([
            'name' => 'Full Integrity Supplier',
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $this->cashCustomer = Customer::query()->create([
            'name' => 'Full Cash',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $this->creditCustomer = Customer::query()->create([
            'name' => 'Full Credit',
            'type' => 'credit',
            'phone' => null,
            'address' => null,
            'credit_limit' => 50000,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (! self::$reportWritten) {
            DashboardIntegrityReportWriter::write(self::REPORT_PATH);
            self::$reportWritten = true;
        }
    }

    public function test_full_system_flow_with_arabic_integrity_report(): void
    {
        try {
            $auth = fn (): static => $this->withToken($this->token);

            // 1) Capital
            $this->runStep(
                'تعيين رأس المال',
                ['business_capital'],
                fn () => $auth()->putJson('/api/v1/settings/capital', [
                    'capital_amount' => 500000,
                    'reason' => 'Opening capital',
                    'branch_id' => $this->branch->id,
                ])->assertOk(),
            );
            $this->assertAccountingIntegrity(['business_capital' => 500000.0]);
            $this->assertEquals(3000.0, $this->stockValueAtCost());

            // 2) Add part + inventory adjust
            $partId = null;
            $this->runStep(
                'إضافة قطعة وتعديل مخزون',
                ['total_stock_value_cost'],
                function () use ($auth, &$partId) {
                    $create = $auth()->postJson('/api/v1/parts?branch_id='.$this->branch->id, [
                        'code' => 'FULL-INTAKE',
                        'name' => 'Intake Part',
                        'category_key' => 'compressor',
                        'unit' => 'pc',
                        'sell_price' => 120,
                        'cost_price' => 50,
                        'min_stock' => 0,
                        'initial_quantity' => 10,
                    ])->assertCreated();
                    $partId = (string) $create->json('id');

                    $auth()->postJson('/api/v1/inventory/adjust', [
                        'part_id' => $partId,
                        'branch_id' => $this->branch->id,
                        'quantity_delta' => 5,
                        'unit_cost' => 60,
                        'reason' => 'Extra stock',
                    ])->assertOk();
                },
            );
            $stockAfterIntake = $this->stockValueAtCost();
            $this->assertAccountingIntegrity();
            $this->assertGreaterThan(3000.0, $stockAfterIntake);

            // 3) Purchase + receive + pay installment
            $poId = null;
            $this->runStep(
                'شراء واستلام ودفع قسط مورد',
                ['must_pay_suppliers', 'period_supplier_payments', 'total_stock_value_cost'],
                function () use ($auth, &$poId) {
                    $poId = (string) $auth()->postJson('/api/v1/purchases', [
                        'supplier_id' => $this->supplier->id,
                        'branch_id' => $this->branch->id,
                        'payment_type' => 'installments',
                        'installment_count' => 2,
                        'installment_start_date' => now()->toDateString(),
                        'items' => [
                            ['part_id' => $this->part->id, 'quantity' => 5, 'unit_cost' => 100],
                        ],
                    ])->assertCreated()->json('id');

                    $auth()->patchJson("/api/v1/purchases/{$poId}/receive", [
                        'branch_id' => $this->branch->id,
                    ])->assertOk();

                    $installmentId = (string) $auth()->getJson("/api/v1/purchases/{$poId}")
                        ->json('installments.0.id');

                    $auth()->postJson("/api/v1/installments/{$installmentId}/pay", [
                        'payment_method' => 'cash',
                    ])->assertOk();
                },
            );
            $this->assertAccountingIntegrity([
                'must_pay_suppliers' => 250.0,
                'period_supplier_payments' => 250.0,
            ]);
            $this->assertEqualsWithDelta($stockAfterIntake + 500.0, $this->stockValueAtCost(), 0.05);

            $cashAfterSupplierPay = (float) $this->dashboardSummary()['cash_on_hand_realized'];

            // 4) Cash sale
            $this->runStep(
                'بيع نقدي',
                ['period_revenue', 'period_profit', 'cash_on_hand_realized'],
                fn () => $auth()->postJson('/api/v1/invoices', [
                    'customer_id' => $this->cashCustomer->id,
                    'branch_id' => $this->branch->id,
                    'payment_type' => 'cash',
                    'items' => [['part_id' => $this->part->id, 'quantity' => 2]],
                ])->assertCreated(),
            );
            $this->assertAccountingIntegrity([
                'period_revenue' => 400.0,
                'period_profit' => 200.0,
            ]);
            $this->assertGreaterThan($cashAfterSupplierPay, (float) $this->dashboardSummary()['cash_on_hand_realized']);

            // 5) Credit sale + partial payment
            $creditInvoiceId = null;
            $this->runStep(
                'بيع آجل ودفعة جزئية',
                ['must_collect_customers', 'cash_on_hand_realized'],
                function () use ($auth, &$creditInvoiceId) {
                    $creditInvoiceId = (string) $auth()->postJson('/api/v1/invoices', [
                        'customer_id' => $this->creditCustomer->id,
                        'branch_id' => $this->branch->id,
                        'payment_type' => 'credit',
                        'items' => [['part_id' => $this->part->id, 'quantity' => 1]],
                    ])->assertCreated()->json('id');

                    $auth()->postJson("/api/v1/customers/{$this->creditCustomer->id}/payments", [
                        'payment_method' => 'cash',
                        'amount' => 80,
                    ])->assertCreated();
                },
            );
            $this->assertAccountingIntegrity(['must_collect_customers' => 120.0]);

            // 6) Return refund cash
            $this->runStep(
                'مرتجع عميل (استرداد نقدي)',
                ['period_customer_refunds', 'period_profit'],
                function () use ($auth, $creditInvoiceId) {
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
                        'resolution' => 'refund_cash',
                    ])->assertOk();
                },
            );
            $this->assertAccountingIntegrity([
                'period_customer_refunds' => 200.0,
                'must_collect_customers' => 120.0,
            ]);

            // 7) Owner cash-out
            $this->runStep(
                'سحب نقدي للمالك',
                ['withdrawable_profit', 'cash_on_hand_realized'],
                fn () => $auth()->postJson('/api/v1/settings/capital/cash-out', [
                    'amount' => 50,
                    'reason' => 'Owner draw',
                    'branch_id' => $this->branch->id,
                ])->assertOk(),
            );
            $this->assertAccountingIntegrity(['total_owner_cash_outs' => 50.0]);

            $orgStockBeforeTransfer = $this->stockValueAtCost();
            $cashBeforeBranchFinance = (float) $this->dashboardSummary()['cash_on_hand_realized'];
            $stockBranchABefore = $this->stockValueAtCost($this->branch->id);
            $stockBranchBBefore = $this->stockValueAtCost($this->branchB->id);

            // 8) Branch transfer complete (goods)
            $transferId = null;
            $this->runStep(
                'إتمام تحويل بضائع بين الفروع',
                ['total_stock_value_cost', 'cash_on_hand_realized'],
                function () use ($auth, &$transferId) {
                    $transferId = (string) $auth()->postJson('/api/v1/transfers', [
                        'from_branch_id' => $this->branch->id,
                        'to_branch_id' => $this->branchB->id,
                        'items' => [['part_id' => $this->part->id, 'quantity' => 4]],
                    ])->assertCreated()->json('id');

                    $auth()->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();
                },
                extraAfter: fn (): array => [
                    'stock_branch_a' => $this->stockValueAtCost($this->branch->id),
                    'stock_branch_b' => $this->stockValueAtCost($this->branchB->id),
                    'branch_balance_owed' => $this->branchBalanceOwed(),
                ],
                extraBefore: [
                    'stock_branch_a' => $stockBranchABefore,
                    'stock_branch_b' => $stockBranchBBefore,
                ],
            );

            $this->assertEqualsWithDelta($orgStockBeforeTransfer, $this->stockValueAtCost(), 0.01);
            $this->assertEquals($cashBeforeBranchFinance, (float) $this->dashboardSummary()['cash_on_hand_realized']);
            $this->assertEquals(400.0, $this->branchBalanceOwed());

            // 9) Branch payment (money) — dashboard cash unchanged
            $this->runStep(
                'دفع مالي بين الفروع',
                ['cash_on_hand_realized'],
                fn () => $auth()->postJson('/api/v1/branch-finance/payments', [
                    'creditor_branch_id' => $this->branch->id,
                    'debtor_branch_id' => $this->branchB->id,
                    'amount' => 150,
                ])->assertCreated(),
                extraAfter: fn (): array => ['branch_balance_owed' => $this->branchBalanceOwed()],
                extraBefore: fn (): array => ['branch_balance_owed' => 400.0],
            );
            $this->assertEquals(250.0, $this->branchBalanceOwed());
            $this->assertEquals($cashBeforeBranchFinance, (float) $this->dashboardSummary()['cash_on_hand_realized']);

            // 10) Edit pending transfer + complete
            $editableTransferId = null;
            $this->runStep(
                'تعديل تحويل معلق ثم إتمامه',
                ['total_stock_value_cost'],
                function () use ($auth, &$editableTransferId) {
                    $editableTransferId = (string) $auth()->postJson('/api/v1/transfers', [
                        'from_branch_id' => $this->branch->id,
                        'to_branch_id' => $this->branchB->id,
                        'items' => [['part_id' => $this->part->id, 'quantity' => 3]],
                    ])->assertCreated()->json('id');

                    $auth()->patchJson("/api/v1/transfers/{$editableTransferId}", [
                        'items' => [['part_id' => $this->part->id, 'quantity' => 2]],
                    ])->assertOk();

                    $auth()->patchJson("/api/v1/transfers/{$editableTransferId}/complete")->assertOk();
                },
                extraAfter: fn (): array => ['branch_balance_owed' => $this->branchBalanceOwed()],
            );
            $this->assertEquals(450.0, $this->branchBalanceOwed());

            // 11) Reverse completed transfer
            $stockBeforeReverse = $this->stockValueAtCost();
            $this->runStep(
                'عكس تحويل مكتمل',
                ['total_stock_value_cost'],
                fn () => $auth()->patchJson("/api/v1/transfers/{$editableTransferId}/reverse")->assertOk(),
                extraAfter: fn (): array => ['branch_balance_owed' => $this->branchBalanceOwed()],
            );
            $this->assertEqualsWithDelta($stockBeforeReverse, $this->stockValueAtCost(), 0.05);
            $this->assertEquals(250.0, $this->branchBalanceOwed());

            // 12) Edit / void branch finance entry
            $manualChargeId = (string) $auth()->postJson('/api/v1/branch-finance/charges', [
                'creditor_branch_id' => $this->branch->id,
                'debtor_branch_id' => $this->branchB->id,
                'amount' => 100,
            ])->json('id');

            $this->runStep(
                'تعديل وإلغاء قيد مالي بين الفروع',
                ['cash_on_hand_realized'],
                function () use ($auth, $manualChargeId) {
                    $auth()->patchJson("/api/v1/branch-finance/entries/{$manualChargeId}", [
                        'amount' => 80,
                    ])->assertOk();

                    $auth()->deleteJson("/api/v1/branch-finance/entries/{$manualChargeId}")->assertNoContent();
                },
                extraBefore: fn (): array => ['branch_balance_owed' => $this->branchBalanceOwed()],
                extraAfter: fn (): array => ['branch_balance_owed' => $this->branchBalanceOwed()],
            );
            $this->assertEquals(250.0, $this->branchBalanceOwed());

            // 13) Soft-delete part
            $this->runStep(
                'حذف صنف من الكتالوج',
                ['total_stock_value_cost'],
                fn () => $auth()->deleteJson('/api/v1/parts/'.$partId)->assertNoContent(),
            );

            // 14) Period filters
            $this->runStep(
                'فلاتر الفترة (يوم / أسبوع / شهر)',
                ['period_revenue'],
                function () use ($auth) {
                    foreach (['day', 'week', 'month'] as $period) {
                        $summary = $auth()->getJson("/api/v1/dashboard/summary?period={$period}")
                            ->assertOk()
                            ->json();
                        $this->assertSame($period, $summary['period']['key']);
                        $this->assertGreaterThan(0, (float) $summary['period_revenue']);
                    }
                },
            );

            $this->assertAccountingIntegrity();
        } catch (Throwable $e) {
            DashboardIntegrityReportWriter::markFailed();
            throw $e;
        } finally {
            if (! self::$reportWritten) {
                DashboardIntegrityReportWriter::write(self::REPORT_PATH);
                self::$reportWritten = true;
            }
        }
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, float|int>  $expectedSummary
     */
    private function assertAccountingIntegrity(array $expectedSummary = []): void
    {
        Cache::flush();

        $dbStockValue = $this->stockValueAtCost();
        $summary = $this->dashboardSummary();

        $this->assertEquals(
            $dbStockValue,
            (float) $summary['total_stock_value_cost'],
            'Dashboard total_stock_value_cost must equal stock table.'
        );

        foreach ($expectedSummary as $field => $expected) {
            $actual = (float) $summary[$field];
            if (str_contains($field, 'stock') || str_contains($field, 'profit') || str_contains($field, 'revenue')) {
                $this->assertEqualsWithDelta($expected, $actual, 0.05, "Dashboard summary [{$field}] mismatch.");
            } else {
                $this->assertEquals($expected, $actual, "Dashboard summary [{$field}] mismatch.");
            }
        }
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>|callable(): array<string, mixed>  $extraBefore
     * @param  array<string, mixed>|callable(): array<string, mixed>  $extraAfter
     */
    private function runStep(
        string $actionAr,
        array $fields,
        callable $callback,
        array|callable $extraBefore = [],
        array|callable $extraAfter = [],
    ): void {
        Cache::flush();
        $before = array_merge($this->pickSummaryFields($fields), $this->resolveStepExtras($extraBefore));

        $callback();

        Cache::flush();
        $after = array_merge($this->pickSummaryFields($fields), $this->resolveStepExtras($extraAfter));

        DashboardIntegrityReportWriter::recordStep($actionAr, array_keys($before), $before, $after);
    }

    /**
     * @param  array<string, mixed>|callable(): array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function resolveStepExtras(array|callable $extras): array
    {
        return is_callable($extras) ? $extras() : $extras;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function pickSummaryFields(array $fields): array
    {
        $summary = $this->dashboardSummary();
        $picked = [];
        foreach ($fields as $field) {
            $picked[$field] = isset($summary[$field]) ? (float) $summary[$field] : null;
        }

        return $picked;
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

    private function branchBalanceOwed(): float
    {
        $balances = $this->withToken($this->token)
            ->getJson('/api/v1/branch-finance/balances')
            ->assertOk()
            ->json('balances');

        $row = collect($balances)->first(
            fn (array $r) => $r['creditor_branch_id'] === $this->branch->id
                && $r['debtor_branch_id'] === $this->branchB->id
        );

        return (float) ($row['balance_owed'] ?? 0);
    }
}
