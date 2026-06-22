<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Stock;
use App\Models\SupplierInstallment;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Support\DashboardPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    private const TTL = 300;

    public function __construct(
        private DashboardCacheService $dashboardCache,
        private FinancialMetricsService $financialMetrics,
        private CapitalService $capital,
        private SupplierRepositoryInterface $suppliers,
    ) {}

    public function summary(?string $branchId = null, ?DashboardPeriod $period = null): array
    {
        $period ??= DashboardPeriod::fromRequest(null, null);
        $from = $period->from;
        $to = $period->to;
        $cacheKey = $this->dashboardCache->keySummary($branchId, $period->cacheSuffix());

        if ($branchId !== null) {
            $this->dashboardCache->rememberBranchKey($branchId);
        }

        return Cache::remember($cacheKey, self::TTL, function () use ($from, $to, $branchId, $period) {
            $capitalSetting = $this->capital->settings();
            $openingCash = $this->capital->capitalAmount($branchId);
            $cashSnapshot = $this->capital->realizedCashSnapshot($openingCash, $from, $to, $branchId);
            $capitalSnapshot = $this->capital->financingSnapshot(
                $openingCash,
                $branchId,
                $cashSnapshot['cash_on_hand_realized'],
            );
            $profitWithdrawal = $this->capital->profitWithdrawalSnapshot($branchId);

            $metrics = $this->financialMetrics->totals($from, $to, $branchId);
            $supplierMetrics = $this->financialMetrics->supplierMetrics($from, $to, $branchId);

            return [
                'period' => $period->toArray(),
                'branch_id' => $branchId,
                'total_receivables' => $capitalSnapshot['customer_receivables'],
                'total_supplier_debt' => $capitalSnapshot['supplier_debt'],
                'total_stock_value_cost' => $capitalSnapshot['inventory_at_cost'],
                'business_capital' => $capitalSnapshot['business_capital'],
                'opening_cash_balance' => $openingCash,
                'capital_currency' => $capitalSetting->currency,
                'capital_estimated_available' => $cashSnapshot['cash_on_hand_realized'],
                'withdrawable_profit' => $profitWithdrawal['withdrawable_profit'],
                'realized_profit' => $profitWithdrawal['realized_profit'],
                'total_owner_cash_outs' => $profitWithdrawal['total_withdrawn'],
                'must_collect_customers' => $cashSnapshot['must_collect_customers'],
                'must_pay_suppliers' => $cashSnapshot['must_pay_suppliers'],
                'cash_on_hand_realized' => $cashSnapshot['cash_on_hand_realized'],
                'lifetime_cash_in_realized' => $cashSnapshot['lifetime_cash_in_realized'],
                'lifetime_cash_out_realized' => $cashSnapshot['lifetime_cash_out_realized'],
                'period_cash_in_realized' => $cashSnapshot['period_cash_in_realized'],
                'period_cash_out_realized' => $cashSnapshot['period_cash_out_realized'],
                'period_net_cash_flow_realized' => $cashSnapshot['period_net_cash_flow_realized'],
                'weekly_cash_in_realized' => $cashSnapshot['period_cash_in_realized'],
                'weekly_cash_out_realized' => $cashSnapshot['period_cash_out_realized'],
                'weekly_net_cash_flow_realized' => $cashSnapshot['period_net_cash_flow_realized'],
                'legacy_estimated_available' => $capitalSnapshot['legacy_estimated_available'],
                'period_revenue' => $metrics['revenue'],
                'period_discount' => $metrics['discount'],
                'period_customer_refunds' => $metrics['customer_refunds'],
                'period_net_sales' => $metrics['net_sales'],
                'period_gross_profit' => $metrics['gross_profit'],
                'period_customer_refund_profit_impact' => $metrics['customer_refund_profit_impact'],
                'period_profit' => $metrics['profit'],
                'period_supplier_payments' => $supplierMetrics['weekly_supplier_payments'],
                'period_purchases_ordered' => $supplierMetrics['weekly_purchases_ordered'],
                'period_purchases_received' => $supplierMetrics['weekly_purchases_received'],
                'weekly_revenue' => $metrics['revenue'],
                'weekly_discount' => $metrics['discount'],
                'weekly_customer_refunds' => $metrics['customer_refunds'],
                'weekly_net_sales' => $metrics['net_sales'],
                'weekly_gross_profit' => $metrics['gross_profit'],
                'weekly_customer_refund_profit_impact' => $metrics['customer_refund_profit_impact'],
                'weekly_profit' => $metrics['profit'],
                'weekly_supplier_payments' => $supplierMetrics['weekly_supplier_payments'],
                'weekly_purchases_ordered' => $supplierMetrics['weekly_purchases_ordered'],
                'weekly_purchases_received' => $supplierMetrics['weekly_purchases_received'],
                'unpaid_installments_total' => $supplierMetrics['unpaid_installments_total'],
                'overdue_installments_total' => $supplierMetrics['overdue_installments_total'],
                'unpaid_installments_count' => $supplierMetrics['unpaid_installments_count'],
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    public function cashSnapshot(
        float $openingCashBalance,
        \Carbon\CarbonInterface $from,
        \Carbon\CarbonInterface $to,
        ?string $branchId = null
    ): array {
        return $this->capital->realizedCashSnapshot($openingCashBalance, $from, $to, $branchId);
    }

    public function inventory(?string $branchId = null): array
    {
        $query = Stock::query()->with(['part', 'branch'])->forActiveParts();

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->get()
            ->map(fn (Stock $s) => [
                'part_id' => $s->part_id,
                'part_code' => $s->part?->code,
                'branch_id' => $s->branch_id,
                'branch_name' => $s->branch?->name,
                'quantity' => $s->quantity,
                'average_cost' => (float) $s->average_cost,
                'value_at_cost' => (float) bcmul((string) $s->quantity, (string) $s->average_cost, 2),
                'min_stock' => $s->part?->min_stock,
                'low' => $s->part && $s->quantity < $s->part->min_stock,
            ])
            ->values()
            ->all();
    }

    public function receivables(?string $branchId = null): array
    {
        $customerQuery = Customer::query()->where('type', 'credit');

        if ($branchId !== null) {
            $customerQuery->whereHas('invoices', fn ($q) => $q
                ->where('branch_id', $branchId)
                ->where('payment_type', 'credit')
                ->where('is_paid', false));
        }

        return $customerQuery
            ->get()
            ->map(function (Customer $c) use ($branchId) {
                $invoiceQuery = Invoice::query()
                    ->where('customer_id', $c->id)
                    ->where('payment_type', 'credit')
                    ->where('is_paid', false);

                if ($branchId !== null) {
                    $invoiceQuery->where('branch_id', $branchId);
                }

                $unpaid = $invoiceQuery->get();
                $balance = (float) $unpaid->sum(fn (Invoice $invoice) => (float) $invoice->balanceDue());

                return [
                    'customer_id' => $c->id,
                    'name' => $c->name,
                    'outstanding_balance' => $balance,
                    'unpaid_invoices' => $unpaid->count(),
                ];
            })
            ->filter(fn (array $row) => $row['outstanding_balance'] > 0 || $row['unpaid_invoices'] > 0)
            ->values()
            ->all();
    }

    public function payables(?string $branchId = null): array
    {
        $upcomingQuery = SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('due_date');

        $overdueQuery = SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['supplier'])
            ->orderBy('due_date');

        if ($branchId !== null) {
            $upcomingQuery->whereHas('purchaseOrder', fn ($po) => $po->where('branch_id', $branchId));
            $overdueQuery->whereHas('purchaseOrder', fn ($po) => $po->where('branch_id', $branchId));
        }

        return [
            'upcoming_30_days' => $upcomingQuery->get(),
            'overdue' => $overdueQuery->get(),
        ];
    }

    /**
     * @return list<array{supplier: \App\Models\Supplier, purchase_orders: \Illuminate\Database\Eloquent\Collection, installments: \Illuminate\Database\Eloquent\Collection}>
     */
    public function payablesBySupplier(?string $branchId = null): array
    {
        return $this->suppliers->debtsWithBalance($branchId);
    }

    public function sales(?string $branchId = null, ?DashboardPeriod $period = null): array
    {
        $period ??= DashboardPeriod::fromRequest(null, null);
        $from = $period->from;
        $to = $period->to;

        $categoryQuery = Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('parts', 'parts.id', '=', 'invoice_items.part_id')
            ->join('part_categories', 'part_categories.id', '=', 'parts.category_id')
            ->where('invoices.created_at', '>=', $from)
            ->where('invoices.created_at', '<=', $to);

        if ($branchId !== null) {
            $categoryQuery->where('invoices.branch_id', $branchId);
        }

        $byCategory = $categoryQuery
            ->selectRaw('part_categories.key as category_key, part_categories.name as category, SUM(invoice_items.total) as total')
            ->groupBy('part_categories.id', 'part_categories.key', 'part_categories.name')
            ->get();

        $branchQuery = Invoice::query()
            ->join('branches', 'branches.id', '=', 'invoices.branch_id')
            ->where('invoices.created_at', '>=', $from)
            ->where('invoices.created_at', '<=', $to);

        if ($branchId !== null) {
            $branchQuery->where('invoices.branch_id', $branchId);
        }

        $byBranchRaw = $branchQuery
            ->selectRaw('branches.id as branch_id, branches.name, SUM(invoices.subtotal) as revenue, SUM(invoices.discount) as discount, SUM(invoices.total) as total')
            ->groupBy('branches.id', 'branches.name')
            ->get();

        $refundsByBranch = $this->financialMetrics->customerRefundsByBranchId($from, $to);

        $byBranch = $byBranchRaw->map(function ($row) use ($from, $to) {
            $refunds = (float) ($refundsByBranch[$row->branch_id] ?? 0);
            $branchMetrics = $this->financialMetrics->totals($from, $to, $row->branch_id);

            return (object) [
                'branch_id' => $row->branch_id,
                'name' => $row->name,
                'total' => (float) $row->total,
                'revenue' => (float) $row->revenue,
                'discount' => (float) $row->discount,
                'customer_refunds' => $refunds,
                'profit' => $branchMetrics['profit'],
            ];
        });

        $creditQuery = Invoice::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($branchId !== null) {
            $creditQuery->where('branch_id', $branchId);
        }

        $creditVsCash = $creditQuery
            ->selectRaw('payment_type, SUM(total) as total')
            ->groupBy('payment_type')
            ->get();

        $totals = $this->financialMetrics->totals($from, $to, $branchId);

        return [
            'period' => $period->toArray(),
            'totals' => $totals,
            'by_category' => $byCategory,
            'by_branch' => $byBranch,
            'credit_vs_cash' => $creditVsCash,
        ];
    }

    public function activity(): array
    {
        return DB::table('audit_logs')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->all();
    }
}
