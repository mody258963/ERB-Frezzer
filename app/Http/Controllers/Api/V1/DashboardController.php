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
use App\Services\CapitalService;
use App\Services\DashboardQueryService;
use App\Support\BranchVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardQueryService $dashboard,
        private CapitalService $capital,
    ) {}

    public function summary(BranchScopedRequest $request): DashboardSummaryResource
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return new DashboardSummaryResource($this->dashboard->summary($branchId));
    }

    public function cash(BranchScopedRequest $request): JsonResponse
    {
        $branchId = BranchVisibility::activeBranchId($request->user());
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();
        $capitalAmount = $this->capital->capitalAmount($branchId);

        return response()->json($this->dashboard->cashSnapshot($capitalAmount, $from, $to, $branchId));
    }

    public function inventory(BranchScopedRequest $request): AnonymousResourceCollection
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return DashboardInventoryRowResource::collection(collect($this->dashboard->inventory($branchId)));
    }

    public function receivables(BranchScopedRequest $request): AnonymousResourceCollection
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return DashboardReceivableRowResource::collection(collect($this->dashboard->receivables($branchId)));
    }

    public function payables(BranchScopedRequest $request): DashboardPayablesResource
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return new DashboardPayablesResource($this->dashboard->payables($branchId));
    }

    public function sales(BranchScopedRequest $request): DashboardSalesResource
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return new DashboardSalesResource($this->dashboard->sales($branchId));
    }

    public function activity(): AnonymousResourceCollection
    {
        return AuditActivityRowResource::collection(collect($this->dashboard->activity()));
    }
}
