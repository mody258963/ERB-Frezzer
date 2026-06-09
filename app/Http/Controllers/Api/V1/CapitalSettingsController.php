<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Capital\OwnerCashOutRequest;
use App\Http\Requests\Api\V1\Capital\UpdateCapitalRequest;
use App\Http\Resources\CapitalAdjustmentResource;
use App\Http\Resources\CapitalSettingsResource;
use App\Http\Resources\OwnerCashOutResource;
use App\Http\Resources\OwnerCashOutResultResource;
use App\Services\AuditLogService;
use App\Services\CapitalService;
use App\Services\DashboardCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CapitalSettingsController extends Controller
{
    public function __construct(
        private CapitalService $capital,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    public function show(): CapitalSettingsResource
    {
        return new CapitalSettingsResource($this->capital->showWithSnapshot());
    }

    public function update(UpdateCapitalRequest $request): CapitalSettingsResource
    {
        $data = $request->validated();
        $before = $this->capital->showWithSnapshot();

        $setting = $this->capital->update(
            $request->user(),
            (float) $data['capital_amount'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );
        $after = $this->capital->showWithSnapshot();

        $this->audit->record(
            $request->user(),
            'settings.capital.update',
            'company_settings',
            $setting->id,
            $before,
            $after,
        );

        $this->dashboardCache->forgetAllSummaries();

        return new CapitalSettingsResource($after);
    }

    public function adjustments(Request $request): AnonymousResourceCollection
    {
        return CapitalAdjustmentResource::collection(
            $this->capital->adjustments((int) $request->query('per_page', 25))
        );
    }

    public function cashOut(OwnerCashOutRequest $request): OwnerCashOutResultResource
    {
        $data = $request->validated();
        $before = $this->capital->showWithSnapshot();

        $result = $this->capital->cashOut(
            $request->user(),
            (float) $data['amount'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
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
        return OwnerCashOutResource::collection(
            $this->capital->cashOuts((int) $request->query('per_page', 25))
        );
    }
}
