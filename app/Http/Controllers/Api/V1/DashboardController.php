<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditActivityRowResource;
use App\Http\Resources\DashboardInventoryRowResource;
use App\Http\Resources\DashboardPayablesResource;
use App\Http\Resources\DashboardReceivableRowResource;
use App\Http\Resources\DashboardSalesResource;
use App\Http\Resources\DashboardSummaryResource;
use App\Services\DashboardQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardQueryService $dashboard
    ) {}

    public function summary(Request $request): DashboardSummaryResource
    {
        $request->validate([
            'branch_id' => ['nullable', 'uuid'],
        ]);

        return new DashboardSummaryResource(
            $this->dashboard->summary($request->query('branch_id'))
        );
    }

    public function inventory(Request $request): AnonymousResourceCollection
    {
        return DashboardInventoryRowResource::collection(collect($this->dashboard->inventory()));
    }

    public function receivables(Request $request): AnonymousResourceCollection
    {
        return DashboardReceivableRowResource::collection(collect($this->dashboard->receivables()));
    }

    public function payables(Request $request): DashboardPayablesResource
    {
        return new DashboardPayablesResource($this->dashboard->payables());
    }

    public function sales(Request $request): DashboardSalesResource
    {
        $request->validate([
            'branch_id' => ['nullable', 'uuid'],
        ]);

        return new DashboardSalesResource(
            $this->dashboard->sales($request->query('branch_id'))
        );
    }

    public function activity(Request $request): AnonymousResourceCollection
    {
        return AuditActivityRowResource::collection(collect($this->dashboard->activity()));
    }
}
