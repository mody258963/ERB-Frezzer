<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaturdaySettlementResource;
use App\Http\Resources\SettlementUpcomingRowResource;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use App\Services\SaturdaySettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettlementController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'settlement_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,check'],
            'notes' => ['nullable', 'string'],
        ]);

        $s = $this->settlementService->create($request->user(), $data);

        return (new SaturdaySettlementResource($this->settlements->findWithInvoices($s->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): SaturdaySettlementResource
    {
        $s = $this->settlements->findWithInvoices($id);
        abort_if(! $s, 404);

        return new SaturdaySettlementResource($s);
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        return SettlementUpcomingRowResource::collection($this->settlements->upcomingTotals());
    }
}
