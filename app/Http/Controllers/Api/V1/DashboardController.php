<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\BranchScopedRequest;
use App\Http\Resources\AuditActivityRowResource;
use App\Http\Resources\DashboardInventoryRowResource;
use App\Http\Resources\DashboardPayablesResource;
use App\Http\Resources\DashboardReceivableRowResource;
use App\Http\Resources\DashboardSalesResource;
use App\Http\Resources\DashboardSummaryResource;
use App\Services\DashboardQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardQueryService $dashboard
    ) {}

    public function summary(BranchScopedRequest $request): DashboardSummaryResource
    {
        return new DashboardSummaryResource(
            $this->dashboard->summary($request->validated('branch_id'))
        );
    }

    public function inventory(): AnonymousResourceCollection
    {
        return DashboardInventoryRowResource::collection(collect($this->dashboard->inventory()));
    }

    public function receivables(): AnonymousResourceCollection
    {
        return DashboardReceivableRowResource::collection(collect($this->dashboard->receivables()));
    }

    public function payables(): DashboardPayablesResource
    {
        return new DashboardPayablesResource($this->dashboard->payables());
    }

    public function sales(BranchScopedRequest $request): DashboardSalesResource
    {
        return new DashboardSalesResource(
            $this->dashboard->sales($request->validated('branch_id'))
        );
    }

    public function activity(): AnonymousResourceCollection
    {
        return AuditActivityRowResource::collection(collect($this->dashboard->activity()));
    }
}
