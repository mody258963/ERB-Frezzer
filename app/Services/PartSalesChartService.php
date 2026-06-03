<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PartSalesChartService
{
    /**
     * Top-selling parts with monthly breakdown for charting (line/bar).
     *
     * @return array<string, mixed>
     */
    public function chart(
        ?User $user,
        int $year,
        ?string $branchId = null,
        int $limit = 10,
        string $rankBy = 'units',
    ): array {
        if ($user?->branch_id && $user->role !== \App\Enums\UserRole::Admin) {
            $branchId = $user->branch_id;
        }
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', invoices.created_at)"
            : "DATE_FORMAT(invoices.created_at, '%Y-%m')";

        $topPartsQuery = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('parts', 'parts.id', '=', 'invoice_items.part_id')
            ->whereDate('invoices.created_at', '>=', $from)
            ->whereDate('invoices.created_at', '<=', $to);

        if ($branchId) {
            $topPartsQuery->where('invoices.branch_id', $branchId);
        }

        $rankColumn = $rankBy === 'revenue' ? 'revenue' : 'units_sold';

        $topParts = $topPartsQuery
            ->selectRaw('invoice_items.part_id')
            ->selectRaw('parts.code')
            ->selectRaw('parts.name')
            ->selectRaw('SUM(invoice_items.quantity) as units_sold')
            ->selectRaw('SUM(invoice_items.total) as revenue')
            ->groupBy('invoice_items.part_id', 'parts.code', 'parts.name')
            ->orderByDesc($rankColumn)
            ->limit(max(1, min($limit, 50)))
            ->get();

        $partIds = $topParts->pluck('part_id')->all();

        $months = $this->monthsInYear($year);
        $monthlyByPart = [];

        if ($partIds !== []) {
            $monthlyQuery = DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->whereIn('invoice_items.part_id', $partIds)
                ->whereDate('invoices.created_at', '>=', $from)
                ->whereDate('invoices.created_at', '<=', $to);

            if ($branchId) {
                $monthlyQuery->where('invoices.branch_id', $branchId);
            }

            $rows = $monthlyQuery
                ->selectRaw('invoice_items.part_id')
                ->selectRaw("{$monthExpr} as month")
                ->selectRaw('SUM(invoice_items.quantity) as units_sold')
                ->selectRaw('SUM(invoice_items.total) as revenue')
                ->groupBy('invoice_items.part_id')
                ->groupByRaw($monthExpr)
                ->get();

            foreach ($rows as $row) {
                $monthlyByPart[$row->part_id][$row->month] = [
                    'units_sold' => (int) $row->units_sold,
                    'revenue' => (float) $row->revenue,
                ];
            }
        }

        $series = $topParts->map(function ($part) use ($months, $monthlyByPart) {
            $byMonth = [];
            foreach ($months as $month) {
                $cell = $monthlyByPart[$part->part_id][$month] ?? ['units_sold' => 0, 'revenue' => 0.0];
                $byMonth[] = [
                    'month' => $month,
                    'units_sold' => $cell['units_sold'],
                    'revenue' => $cell['revenue'],
                ];
            }

            return [
                'part_id' => $part->part_id,
                'code' => $part->code,
                'name' => $part->name,
                'total_units_sold' => (int) $part->units_sold,
                'total_revenue' => (float) $part->revenue,
                'by_month' => $byMonth,
            ];
        })->values()->all();

        return [
            'year' => $year,
            'period' => ['from' => $from, 'to' => $to, 'branch_id' => $branchId],
            'rank_by' => $rankBy,
            'limit' => $limit,
            'months' => $months,
            'series' => $series,
        ];
    }

    /**
     * @return list<string>
     */
    private function monthsInYear(int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = sprintf('%04d-%02d', $year, $m);
        }

        return $months;
    }
}
