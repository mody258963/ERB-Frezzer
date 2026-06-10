<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Capital\OwnerCashOutRequest;
use App\Http\Requests\Api\V1\Capital\UpdateCapitalRequest;
use App\Http\Requests\Api\V1\Dashboard\BranchScopedRequest;
use App\Http\Resources\CapitalAdjustmentResource;
use App\Http\Resources\CapitalSettingsResource;
use App\Http\Resources\OwnerCashOutResource;
use App\Http\Resources\OwnerCashOutResultResource;
use App\Services\AuditLogService;
use App\Services\CapitalService;
use App\Services\DashboardCacheService;
use App\Support\BranchVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CapitalSettingsController extends Controller
{
    public function __construct(
        private CapitalService $capital,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    public function show(BranchScopedRequest $request): CapitalSettingsResource
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return new CapitalSettingsResource($this->capital->showWithSnapshot($branchId));
    }

    public function update(UpdateCapitalRequest $request): CapitalSettingsResource
    {
        $data = $request->validated();
        $branchId = BranchVisibility::resolveBranchId(
            $request->user(),
            $data['branch_id'] ?? $request->query('branch_id'),
        ) ?? BranchVisibility::activeBranchId($request->user());

        $before = $this->capital->showWithSnapshot($branchId);

        $branch = $this->capital->update(
            $request->user(),
            (float) $data['capital_amount'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
            $branchId,
        );
        $after = $this->capital->showWithSnapshot($branch->id);

        $this->audit->record(
            $request->user(),
            'settings.capital.update',
            'branch',
            $branch->id,
            $before,
            $after,
        );

        $this->dashboardCache->forgetAllSummaries();

        return new CapitalSettingsResource($after);
    }

    public function adjustments(Request $request): AnonymousResourceCollection
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return CapitalAdjustmentResource::collection(
            $this->capital->adjustments((int) $request->query('per_page', 25), $branchId)
        );
    }

    public function cashOut(OwnerCashOutRequest $request): OwnerCashOutResultResource
    {
        $data = $request->validated();
        $branchId = $data['branch_id'] ?? BranchVisibility::activeBranchId($request->user());
        $before = $this->capital->showWithSnapshot($branchId);

        $result = $this->capital->cashOut(
            $request->user(),
            (float) $data['amount'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
            $branchId,
        );

        $this->audit->record(
            $request->user(),
            'owner.cash_out',
            'owner_cash_out',
            $result['cash_out']->id,
            $before,
            $result['settings'],
        );

        $this->dashboardCache->forgetAllSummaries();

        return new OwnerCashOutResultResource($result);
    }

    public function cashOuts(Request $request): AnonymousResourceCollection
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return OwnerCashOutResource::collection(
            $this->capital->cashOuts((int) $request->query('per_page', 25), $branchId)
        );
    }
}
