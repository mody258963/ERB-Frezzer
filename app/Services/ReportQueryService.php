<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function sales(?string $from, ?string $to, ?string $branchId, ?string $category): array
    {
        $q = Invoice::query()
            ->with(['branch', 'customer'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($category, fn ($q) => $q->whereHas('items.part', fn ($p) => $p->where('category', $category)));

        return $q->latest()->limit(5000)->get()->all();
    }

    public function inventoryValuation(): array
    {
        return Stock::query()
            ->join('parts', 'parts.id', '=', 'stock.part_id')
            ->selectRaw('stock.part_id, parts.code, parts.name, SUM(stock.quantity) as qty, parts.cost_price, parts.sell_price')
            ->groupBy('stock.part_id', 'parts.code', 'parts.name', 'parts.cost_price', 'parts.sell_price')
            ->get()
            ->map(fn ($r) => [
                'part_id' => $r->part_id,
                'code' => $r->code,
                'name' => $r->name,
                'quantity' => $r->qty,
                'value_cost' => (float) bcmul((string) $r->cost_price, (string) $r->qty, 2),
                'value_sell' => (float) bcmul((string) $r->sell_price, (string) $r->qty, 2),
            ])
            ->all();
    }

    public function customerAging(): array
    {
        return DB::table('customers')
            ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.payment_type', 'credit')
            ->where('invoices.is_paid', false)
            ->selectRaw('customers.id, customers.name, customers.outstanding_balance, MIN(invoices.created_at) as oldest_invoice')
            ->groupBy('customers.id', 'customers.name', 'customers.outstanding_balance')
            ->get()
            ->all();
    }

    public function supplierDebtAging(): array
    {
        return DB::table('suppliers')
            ->select('id', 'name', 'total_debt', 'updated_at')
            ->where('total_debt', '>', 0)
            ->get()
            ->all();
    }

    public function returnsSummary(?string $from, ?string $to): array
    {
        $q = fn () => ProductReturn::query()
            ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from))
            ->when($to, fn ($qq) => $qq->whereDate('created_at', '<=', $to));

        return [
            'total_count' => $q()->count(),
            'total_value' => $q()->sum('total_value'),
            'by_reason' => $q()
                ->selectRaw('reason, COUNT(*) as c')
                ->groupBy('reason')
                ->get(),
        ];
    }
}
