<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAgingReportRowResource;
use App\Http\Resources\InventoryValuationRowResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ReturnsSummaryResource;
use App\Http\Resources\SupplierDebtAgingRowResource;
use App\Http\Resources\FinancialReportResource;
use App\Http\Resources\PartSalesChartResource;
use App\Support\BranchVisibility;
use App\Services\PartSalesChartService;
use App\Services\ReportQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function __construct(
        private ReportQueryService $reports,
        private PartSalesChartService $partSalesChart,
    ) {}

    public function sales(Request $request): AnonymousResourceCollection
    {
        $rows = $this->reports->sales(
            $request->user(),
            $request->query('from'),
            $request->query('to'),
            $request->query('branch_id'),
            $request->query('category')
        );

        return InvoiceResource::collection(collect($rows));
    }

    public function financial(Request $request): FinancialReportResource
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
        ]);

        return new FinancialReportResource(
            $this->reports->financial(
                $request->user(),
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['branch_id'] ?? null,
            )
        );
    }

    public function inventory(Request $request): AnonymousResourceCollection
    {
        return InventoryValuationRowResource::collection(collect($this->reports->inventoryValuation()));
    }

    public function customers(Request $request): AnonymousResourceCollection
    {
        return CustomerAgingReportRowResource::collection(collect($this->reports->customerAging()));
    }

    public function suppliers(Request $request): AnonymousResourceCollection
    {
        return SupplierDebtAgingRowResource::collection(collect($this->reports->supplierDebtAging()));
    }

    public function returns(Request $request): ReturnsSummaryResource
    {
        return new ReturnsSummaryResource(
            $this->reports->returnsSummary($request->query('from'), $request->query('to'))
        );
    }

    public function partsSalesChart(Request $request): PartSalesChartResource
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'branch_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rank_by' => ['nullable', 'in:units,revenue'],
        ]);

        $branchId = BranchVisibility::resolveBranchId(
            $request->user(),
            $filters['branch_id'] ?? null,
        );

        return new PartSalesChartResource(
            $this->partSalesChart->chart(
                $request->user(),
                (int) ($filters['year'] ?? now()->year),
                $branchId,
                (int) ($filters['limit'] ?? 10),
                $filters['rank_by'] ?? 'units',
            )
        );
    }
}
