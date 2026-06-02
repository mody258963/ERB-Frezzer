<?php

namespace App\Services;

use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductReturn;
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
    ) {}

    public function summary(): array
    {
        return Cache::remember($this->dashboardCache->keySummary(), self::TTL, function () {
            $receivables = Customer::query()->sum('outstanding_balance');
            $supplierDebt = Supplier::query()->sum('total_debt');
            $stockValue = Stock::query()
                ->join('parts', 'parts.id', '=', 'stock.part_id')
                ->selectRaw('SUM(stock.quantity * parts.cost_price) as v')
                ->value('v') ?? 0;

            $weekStart = now()->startOfWeek();
            $weekInvoices = Invoice::query()->where('created_at', '>=', $weekStart);

            // Gross sales (before invoice discount) — إيرادات المحل قبل الخصم
            $weeklyRevenue = (float) ((clone $weekInvoices)->sum('subtotal'));
            $weeklyDiscount = (float) ((clone $weekInvoices)->sum('discount'));
            // Net collected from customers — ما يدفعه العميل
            $weeklyNetSales = (float) ((clone $weekInvoices)->sum('total'));

            $weeklyGrossProfit = (float) (Invoice::query()
                ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->join('parts', 'parts.id', '=', 'invoice_items.part_id')
                ->where('invoices.created_at', '>=', $weekStart)
                ->selectRaw('SUM((invoice_items.unit_price - parts.cost_price) * invoice_items.quantity) as p')
                ->value('p') ?? 0);

            // Customer refunds (cash / defective refund / credit note) — مرتجعات العملاء
            $weeklyCustomerRefunds = (float) ProductReturn::query()
                ->where('return_type', ReturnType::CustomerReturn->value)
                ->where('status', ReturnStatus::Completed->value)
                ->where('updated_at', '>=', $weekStart)
                ->whereIn('resolution', [
                    ReturnResolution::RefundCash->value,
                    ReturnResolution::Writeoff->value,
                    ReturnResolution::CreditNote->value,
                ])
                ->sum('total_value');

            $weeklyNetSales = (float) bcsub((string) $weeklyNetSales, (string) $weeklyCustomerRefunds, 2);
            if (bccomp((string) $weeklyNetSales, '0', 2) < 0) {
                $weeklyNetSales = 0.0;
            }

            // Discount and refunds reduce profit, not gross revenue (subtotal)
            $weeklyProfit = (float) bcsub(
                bcsub((string) $weeklyGrossProfit, (string) $weeklyDiscount, 2),
                (string) $weeklyCustomerRefunds,
                2
            );
            if (bccomp((string) $weeklyProfit, '0', 2) < 0) {
                $weeklyProfit = 0.0;
            }

            return [
                'total_receivables' => (float) $receivables,
                'total_supplier_debt' => (float) $supplierDebt,
                'total_stock_value_cost' => (float) $stockValue,
                'weekly_revenue' => $weeklyRevenue,
                'weekly_discount' => $weeklyDiscount,
                'weekly_customer_refunds' => $weeklyCustomerRefunds,
                'weekly_net_sales' => $weeklyNetSales,
                'weekly_gross_profit' => $weeklyGrossProfit,
                'weekly_profit' => $weeklyProfit,
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

    public function sales(): array
    {
        $byCategory = Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('parts', 'parts.id', '=', 'invoice_items.part_id')
            ->join('part_categories', 'part_categories.id', '=', 'parts.category_id')
            ->selectRaw('part_categories.key as category_key, part_categories.name as category, SUM(invoice_items.total) as total')
            ->groupBy('part_categories.id', 'part_categories.key', 'part_categories.name')
            ->get();

        $byBranch = Invoice::query()
            ->join('branches', 'branches.id', '=', 'invoices.branch_id')
            ->selectRaw('branches.name, SUM(invoices.total) as total')
            ->groupBy('branches.id', 'branches.name')
            ->get();

        $creditVsCash = Invoice::query()
            ->selectRaw('payment_type, SUM(total) as total')
            ->groupBy('payment_type')
            ->get();

        return [
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
