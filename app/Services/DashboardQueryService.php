<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    private const TTL = 300;

    public function __construct(
        private DashboardCacheService $dashboardCache,
        private FinancialMetricsService $financialMetrics,
        private CapitalService $capital,
    ) {}

    public function summary(?string $branchId = null): array
    {
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();
        $cacheKey = $this->dashboardCache->keySummary($branchId);

        if ($branchId !== null) {
            $this->dashboardCache->rememberBranchKey($branchId);
        }

        return Cache::remember($cacheKey, self::TTL, function () use ($from, $to, $branchId) {
            $receivables = Customer::query()->sum('outstanding_balance');
            $supplierDebt = Supplier::query()->sum('total_debt');
            $stockValue = Stock::query()
                ->join('parts', 'parts.id', '=', 'stock.part_id')
                ->selectRaw('SUM(stock.quantity * parts.cost_price) as v')
                ->value('v') ?? 0;

            $metrics = $this->financialMetrics->totals($from, $to, $branchId);
            $capitalSetting = $this->capital->settings();
            $capitalAmount = (float) $capitalSetting->capital_amount;
            $capitalSnapshot = $this->capital->financingSnapshot($capitalAmount);

            return [
                'total_receivables' => (float) $receivables,
                'total_supplier_debt' => (float) $supplierDebt,
                'total_stock_value_cost' => (float) $stockValue,
                'business_capital' => $capitalAmount,
                'capital_currency' => $capitalSetting->currency,
                'capital_estimated_available' => $capitalSnapshot['estimated_available'],
                'weekly_revenue' => $metrics['revenue'],
                'weekly_discount' => $metrics['discount'],
                'weekly_customer_refunds' => $metrics['customer_refunds'],
                'weekly_net_sales' => $metrics['net_sales'],
                'weekly_gross_profit' => $metrics['gross_profit'],
                'weekly_profit' => $metrics['profit'],
            ];
        });
    }

    public function inventory(): array
    {
        return Stock::query()
            ->with(['part', 'branch'])
            ->get()
            ->map(fn (Stock $s) => [
                'part_id' => $s->part_id,
                'part_code' => $s->part?->code,
                'branch_id' => $s->branch_id,
                'branch_name' => $s->branch?->name,
                'quantity' => $s->quantity,
                'min_stock' => $s->part?->min_stock,
                'low' => $s->part && $s->quantity < $s->part->min_stock,
            ])
            ->values()
            ->all();
    }

    public function receivables(): array
    {
        return Customer::query()
            ->where('type', 'credit')
            ->get()
            ->map(fn (Customer $c) => [
                'customer_id' => $c->id,
                'name' => $c->name,
                'outstanding_balance' => (float) $c->outstanding_balance,
                'unpaid_invoices' => Invoice::query()
                    ->where('customer_id', $c->id)
                    ->where('payment_type', 'credit')
                    ->where('is_paid', false)
                    ->count(),
            ])
            ->values()
            ->all();
    }

    public function payables(): array
    {
        $upcoming = SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('due_date')
            ->get();

        $overdue = SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereDate('due_date', '<', now()->toDateString())
            ->with(['supplier'])
            ->orderBy('due_date')
            ->get();

        return [
            'upcoming_30_days' => $upcoming,
            'overdue' => $overdue,
        ];
    }

    public function sales(?string $branchId = null): array
    {
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();

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
