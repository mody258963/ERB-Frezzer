<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\PurchaseOrderItem;
use App\Enums\ReturnStatus;
use App\Models\ReturnItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchVisibility;
use App\Transformers\PartTransformer;
use Illuminate\Support\Facades\DB;

class PartAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(
        Part $part,
        ?User $user,
        ?string $from = null,
        ?string $to = null,
        ?string $branchId = null,
    ): array {
        $branchId = $this->resolveBranchFilter($user, $branchId);

        $stockRows = $this->stockByBranch($part->id, $user, $branchId);
        $totalQty = array_sum(array_column($stockRows, 'quantity'));

        $sales = $this->salesMetrics($part, $from, $to, $branchId);
        $purchases = $this->purchaseMetrics($part->id, $from, $to, $branchId);
        $returns = $this->returnMetrics($part->id, $from, $to, $branchId);

        $costPrice = (string) $part->cost_price;
        $sellPrice = (string) $part->sell_price;
        $valueCost = (float) bcmul($costPrice, (string) $totalQty, 2);
        $valueSell = (float) bcmul($sellPrice, (string) $totalQty, 2);
        $estimatedCogs = (float) bcmul($costPrice, (string) $sales['units_sold'], 2);
        $grossProfit = (float) bcsub((string) $sales['revenue'], (string) $estimatedCogs, 2);

        return [
            'part' => PartTransformer::transform($part),
            'period' => [
                'from' => $from,
                'to' => $to,
                'branch_id' => $branchId,
            ],
            'inventory' => [
                'total_quantity' => $totalQty,
                'min_stock' => (int) $part->min_stock,
                'is_below_min_stock' => $totalQty < $part->min_stock,
                'value_at_cost' => $valueCost,
                'value_at_sell' => $valueSell,
                'margin_per_unit' => (float) bcsub($sellPrice, $costPrice, 2),
                'by_branch' => $stockRows,
            ],
            'sales' => array_merge($sales, [
                'estimated_cogs' => $estimatedCogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $sales['revenue'] > 0
                    ? round(($grossProfit / $sales['revenue']) * 100, 2)
                    : 0.0,
            ]),
            'purchases' => $purchases,
            'returns' => $returns,
            'movements' => [
                'by_type' => $this->movementBreakdown($part->id, $from, $to, $branchId),
                'recent' => $this->recentMovements($part->id, $user, $branchId, 25),
            ],
            'sales_by_month' => $this->salesByMonth($part->id, $from, $to, $branchId),
        ];
    }

    private function resolveBranchFilter(?User $user, ?string $branchId): ?string
    {
        if ($user?->branch_id) {
            return $user->branch_id;
        }

        return $branchId;
    }

    /**
     * @return list<array{branch_id: string, branch_name: string|null, quantity: int}>
     */
    private function stockByBranch(string $partId, ?User $user, ?string $branchId): array
    {
        $query = Stock::query()
            ->with('branch')
            ->where('part_id', $partId);

        BranchVisibility::scope($user, $query, 'branch_id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get()->map(fn (Stock $s) => [
            'branch_id' => $s->branch_id,
            'branch_name' => $s->branch?->name,
            'quantity' => (int) $s->quantity,
        ])->values()->all();
    }

    /**
     * @return array{units_sold: int, revenue: float, invoice_count: int}
     */
    private function salesMetrics(Part $part, ?string $from, ?string $to, ?string $branchId): array
    {
        $query = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.part_id', $part->id);

        $this->applyInvoiceFilters($query, $from, $to, $branchId);

        $row = $query->selectRaw('COALESCE(SUM(invoice_items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(invoice_items.total), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT invoices.id) as invoice_count')
            ->first();

        return [
            'units_sold' => (int) ($row->units_sold ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'invoice_count' => (int) ($row->invoice_count ?? 0),
        ];
    }

    /**
     * @return array{units_purchased: int, cost: float, order_count: int}
     */
    private function purchaseMetrics(string $partId, ?string $from, ?string $to, ?string $branchId): array
    {
        $query = PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.po_id')
            ->where('purchase_order_items.part_id', $partId)
            ->whereIn('purchase_orders.status', [
                PurchaseOrderStatus::Partial->value,
                PurchaseOrderStatus::Settled->value,
            ]);

        if ($from) {
            $query->whereDate('purchase_orders.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('purchase_orders.created_at', '<=', $to);
        }
        if ($branchId) {
            $query->where('purchase_orders.branch_id', $branchId);
        }

        $row = $query->selectRaw('COALESCE(SUM(purchase_order_items.quantity), 0) as units_purchased')
            ->selectRaw('COALESCE(SUM(purchase_order_items.total), 0) as cost')
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as order_count')
            ->first();

        return [
            'units_purchased' => (int) ($row->units_purchased ?? 0),
            'cost' => (float) ($row->cost ?? 0),
            'order_count' => (int) ($row->order_count ?? 0),
        ];
    }

    /**
     * @return array{units_returned: int, value: float, return_count: int}
     */
    private function returnMetrics(string $partId, ?string $from, ?string $to, ?string $branchId): array
    {
        $query = ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('return_items.part_id', $partId)
            ->whereIn('returns.status', [ReturnStatus::Approved->value, ReturnStatus::Completed->value]);

        if ($from) {
            $query->whereDate('returns.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('returns.created_at', '<=', $to);
        }
        if ($branchId) {
            $query->where('returns.branch_id', $branchId);
        }

        $row = $query->selectRaw('COALESCE(SUM(return_items.quantity), 0) as units_returned')
            ->selectRaw('COALESCE(SUM(return_items.total), 0) as value')
            ->selectRaw('COUNT(DISTINCT returns.id) as return_count')
            ->first();

        return [
            'units_returned' => (int) ($row->units_returned ?? 0),
            'value' => (float) ($row->value ?? 0),
            'return_count' => (int) ($row->return_count ?? 0),
        ];
    }

    /**
     * @return list<array{movement_type: string, quantity: int}>
     */
    private function movementBreakdown(string $partId, ?string $from, ?string $to, ?string $branchId): array
    {
        $query = StockMovement::query()->where('part_id', $partId);

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->selectRaw('movement_type, SUM(quantity) as quantity')
            ->groupBy('movement_type')
            ->orderBy('movement_type')
            ->get()
            ->map(fn ($r) => [
                'movement_type' => $r->movement_type instanceof StockMovementType
                    ? $r->movement_type->value
                    : (string) $r->movement_type,
                'quantity' => (int) $r->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentMovements(string $partId, ?User $user, ?string $branchId, int $limit): array
    {
        $query = StockMovement::query()
            ->with(['branch:id,name', 'creator:id,name'])
            ->where('part_id', $partId)
            ->orderByDesc('created_at')
            ->limit($limit);

        BranchVisibility::scope($user, $query, 'branch_id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get()->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'movement_type' => $m->movement_type->value,
            'quantity' => (int) $m->quantity,
            'branch_id' => $m->branch_id,
            'branch_name' => $m->branch?->name,
            'reference_id' => $m->reference_id,
            'reference_type' => $m->reference_type,
            'notes' => $m->notes,
            'created_by' => $m->created_by,
            'created_by_name' => $m->creator?->name,
            'created_at' => $m->created_at?->toISOString(),
        ])->all();
    }

    /**
     * @return list<array{month: string, units_sold: int, revenue: float}>
     */
    private function salesByMonth(string $partId, ?string $from, ?string $to, ?string $branchId): array
    {
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', invoices.created_at)"
            : "DATE_FORMAT(invoices.created_at, '%Y-%m')";

        $query = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.part_id', $partId);

        $this->applyInvoiceFilters($query, $from, $to, $branchId);

        return $query
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw('SUM(invoice_items.quantity) as units_sold')
            ->selectRaw('SUM(invoice_items.total) as revenue')
            ->groupByRaw($monthExpr)
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => (string) $r->month,
                'units_sold' => (int) $r->units_sold,
                'revenue' => (float) $r->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyInvoiceFilters($query, ?string $from, ?string $to, ?string $branchId): void
    {
        if ($from) {
            $query->whereDate('invoices.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('invoices.created_at', '<=', $to);
        }
        if ($branchId) {
            $query->where('invoices.branch_id', $branchId);
        }
    }
}
