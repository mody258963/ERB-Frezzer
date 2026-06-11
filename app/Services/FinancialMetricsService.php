<?php

namespace App\Services;

use App\Enums\ReturnReferenceType;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Enums\SettlementPaymentMethod;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\ReturnItem;
use App\Models\SupplierInstallment;
use App\Models\SupplierInstallmentPayment;
use Carbon\CarbonInterface;
class FinancialMetricsService
{
    /** @var list<string> */
    private const CUSTOMER_REFUND_RESOLUTIONS = [
        ReturnResolution::RefundCash->value,
        ReturnResolution::Writeoff->value,
        ReturnResolution::CreditNote->value,
    ];

    /**
     * @return array{
     *     revenue: float,
     *     discount: float,
     *     customer_refunds: float,
     *     net_sales: float,
     *     gross_profit: float,
     *     profit: float
     * }
     */
    public function totals(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId = null,
    ): array {
        $invoiceQuery = Invoice::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($branchId !== null) {
            $invoiceQuery->where('branch_id', $branchId);
        }

        $revenue = (float) (clone $invoiceQuery)->sum('subtotal');
        $discount = (float) (clone $invoiceQuery)->sum('discount');
        $invoiceTotal = (float) (clone $invoiceQuery)->sum('total');

        $grossProfitQuery = Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.created_at', '>=', $from)
            ->where('invoices.created_at', '<=', $to);

        if ($branchId !== null) {
            $grossProfitQuery->where('invoices.branch_id', $branchId);
        }

        $grossProfit = (float) ($grossProfitQuery
            ->selectRaw('SUM((invoice_items.unit_price - invoice_items.unit_cost) * invoice_items.quantity) as p')
            ->value('p') ?? 0);

        $customerRefunds = $this->sumCustomerRefunds($from, $to, $branchId);
        $refundProfitImpact = $this->sumCustomerRefundProfitImpact($from, $to, $branchId);

        $netSales = (float) bcsub((string) $invoiceTotal, (string) $customerRefunds, 2);
        if (bccomp((string) $netSales, '0', 2) < 0) {
            $netSales = 0.0;
        }

        $profit = (float) bcsub(
            bcsub((string) $grossProfit, (string) $discount, 2),
            (string) $refundProfitImpact,
            2
        );
        if (bccomp((string) $profit, '0', 2) < 0) {
            $profit = 0.0;
        }

        return [
            'revenue' => $revenue,
            'discount' => $discount,
            'customer_refunds' => $customerRefunds,
            'customer_refund_profit_impact' => $refundProfitImpact,
            'net_sales' => $netSales,
            'gross_profit' => $grossProfit,
            'profit' => $profit,
        ];
    }

    /**
     * @return list<array{
     *     branch_id: string,
     *     name: string,
     *     revenue: float,
     *     discount: float,
     *     customer_refunds: float,
     *     gross_profit: float,
     *     profit: float
     * }>
     */
    public function byBranch(CarbonInterface $from, CarbonInterface $to): array
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $rows = [];

        foreach ($branches as $branch) {
            $metrics = $this->totals($from, $to, $branch->id);
            $rows[] = [
                'branch_id' => $branch->id,
                'name' => $branch->name,
                'revenue' => $metrics['revenue'],
                'discount' => $metrics['discount'],
                'customer_refunds' => $metrics['customer_refunds'],
                'gross_profit' => $metrics['gross_profit'],
                'profit' => $metrics['profit'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     customer_count: int,
     *     customer_value: float,
     *     supplier_count: int,
     *     supplier_value: float
     * }
     */
    public function returnsBreakdown(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId = null,
    ): array {
        $customerQuery = $this->completedReturnsQuery($from, $to, $branchId)
            ->where('return_type', ReturnType::CustomerReturn->value)
            ->whereIn('resolution', self::CUSTOMER_REFUND_RESOLUTIONS);

        $supplierQuery = $this->completedReturnsQuery($from, $to, $branchId)
            ->where('return_type', ReturnType::SupplierReturn->value);

        return [
            'customer_count' => (int) (clone $customerQuery)->count(),
            'customer_value' => (float) (clone $customerQuery)->sum('total_value'),
            'supplier_count' => (int) (clone $supplierQuery)->count(),
            'supplier_value' => (float) (clone $supplierQuery)->sum('total_value'),
        ];
    }

    private function sumCustomerRefunds(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId,
    ): float {
        return (float) $this->completedReturnsQuery($from, $to, $branchId)
            ->where('return_type', ReturnType::CustomerReturn->value)
            ->whereIn('resolution', self::CUSTOMER_REFUND_RESOLUTIONS)
            ->sum('total_value');
    }

    private function sumCustomerRefundProfitImpact(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId,
    ): float {
        $query = ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->leftJoin('invoice_items', function ($join) {
                $join->on('invoice_items.invoice_id', '=', 'returns.reference_id')
                    ->on('invoice_items.part_id', '=', 'return_items.part_id')
                    ->where('returns.reference_type', ReturnReferenceType::Invoice->value);
            })
            ->where('returns.status', ReturnStatus::Completed->value)
            ->where('returns.return_type', ReturnType::CustomerReturn->value)
            ->whereIn('returns.resolution', self::CUSTOMER_REFUND_RESOLUTIONS)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('returns.completed_at')
                        ->where('returns.completed_at', '>=', $from)
                        ->where('returns.completed_at', '<=', $to);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('returns.completed_at')
                        ->where('returns.updated_at', '>=', $from)
                        ->where('returns.updated_at', '<=', $to);
                });
            });

        if ($branchId !== null) {
            $query->where('returns.branch_id', $branchId);
        }

        return (float) ($query
            ->selectRaw('SUM((return_items.unit_price - COALESCE(return_items.unit_cost, invoice_items.unit_cost, 0)) * return_items.quantity) as impact')
            ->value('impact') ?? 0);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ProductReturn>
     */
    private function completedReturnsQuery(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId,
    ) {
        $query = ProductReturn::query()
            ->where('status', ReturnStatus::Completed->value)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('completed_at')
                        ->where('completed_at', '>=', $from)
                        ->where('completed_at', '<=', $to);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('completed_at')
                        ->where('updated_at', '>=', $from)
                        ->where('updated_at', '<=', $to);
                });
            });

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    /**
     * Customer refunds grouped by branch for dashboard sales breakdown.
     *
     * @return array<string, float>
     */
    /**
     * @return array{
     *     weekly_supplier_payments: float,
     *     weekly_purchases_ordered: float,
     *     weekly_purchases_received: float,
     *     unpaid_installments_total: float,
     *     overdue_installments_total: float,
     *     unpaid_installments_count: int
     * }
     */
    public function supplierMetrics(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId = null,
    ): array {
        $paymentsQuery = SupplierInstallmentPayment::query()
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<=', $to)
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value);

        if ($branchId !== null) {
            $paymentsQuery->whereHas('installment.purchaseOrder', fn ($q) => $q->where('branch_id', $branchId));
        }

        $orderedQuery = PurchaseOrder::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($branchId !== null) {
            $orderedQuery->where('branch_id', $branchId);
        }

        $receivedQuery = PurchaseOrder::query()
            ->whereNotNull('received_at')
            ->where('received_at', '>=', $from)
            ->where('received_at', '<=', $to);

        if ($branchId !== null) {
            $receivedQuery->where('branch_id', $branchId);
        }

        $unpaidQuery = SupplierInstallment::query()
            ->whereColumn('amount_paid', '<', 'amount');
        if ($branchId !== null) {
            $unpaidQuery->whereHas('purchaseOrder', fn ($q) => $q->where('branch_id', $branchId));
        }

        $overdueQuery = (clone $unpaidQuery)->whereDate('due_date', '<', now()->toDateString());

        return [
            'weekly_supplier_payments' => (float) (clone $paymentsQuery)->sum('amount'),
            'weekly_purchases_ordered' => (float) (clone $orderedQuery)->sum('total_amount'),
            'weekly_purchases_received' => (float) (clone $receivedQuery)->sum('total_amount'),
            'unpaid_installments_total' => (float) (clone $unpaidQuery)
                ->selectRaw('SUM(amount - amount_paid) as balance')
                ->value('balance'),
            'overdue_installments_total' => (float) (clone $overdueQuery)
                ->selectRaw('SUM(amount - amount_paid) as balance')
                ->value('balance'),
            'unpaid_installments_count' => (int) (clone $unpaidQuery)->count(),
        ];
    }

    public function customerRefundsByBranchId(CarbonInterface $from, CarbonInterface $to): array
    {
        return ProductReturn::query()
            ->where('status', ReturnStatus::Completed->value)
            ->where('return_type', ReturnType::CustomerReturn->value)
            ->whereIn('resolution', self::CUSTOMER_REFUND_RESOLUTIONS)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('completed_at')
                        ->where('completed_at', '>=', $from)
                        ->where('completed_at', '<=', $to);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('completed_at')
                        ->where('updated_at', '>=', $from)
                        ->where('updated_at', '<=', $to);
                });
            })
            ->selectRaw('branch_id, SUM(total_value) as refunds')
            ->groupBy('branch_id')
            ->pluck('refunds', 'branch_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
