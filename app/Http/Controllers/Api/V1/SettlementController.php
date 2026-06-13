<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settlement\StoreSettlementRequest;
use App\Http\Resources\SaturdaySettlementResource;
use App\Http\Resources\SettlementUpcomingRowResource;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use App\Services\SaturdaySettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettlementController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private SaturdaySettlementRepositoryInterface $settlements,
        private SaturdaySettlementService $settlementService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SaturdaySettlementResource::collection(
            $this->settlements->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function store(StoreSettlementRequest $request): JsonResponse
    {
        $settlement = $this->settlementService->create($request->user(), $request->validated());

        return (new SaturdaySettlementResource($this->settlements->findWithInvoices($settlement->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): SaturdaySettlementResource
    {
        return new SaturdaySettlementResource(
            $this->resolveOrFail($this->settlements->findWithInvoices($id))
        );
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $cycle = $request->query('settlement_cycle');

        if ($cycle !== null && ! in_array($cycle, ['daily', 'weekly'], true)) {
            abort(422, 'settlement_cycle must be daily or weekly.');
        }

        return SettlementUpcomingRowResource::collection(
            $this->settlements->upcomingTotals($cycle),
        );
    }
}
