<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockTransfer\CompleteStockTransferRequest;
use App\Http\Requests\Api\V1\StockTransfer\StoreStockTransferRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\StockTransferResource;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockTransferController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private StockTransferService $transferService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StockTransferResource::collection(
            $this->transfers->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        $transfer = $this->transfers->create(
            [
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ],
            $data['items']
        );

        return (new StockTransferResource($transfer))->response()->setStatusCode(201);
    }

    public function show(string $id): StockTransferResource
    {
        return new StockTransferResource($this->resolveOrFail($this->transfers->findWithItems($id)));
    }

    public function complete(CompleteStockTransferRequest $request, string $id): StockTransferResource
    {
        $options = $request->validated();

        return new StockTransferResource($this->transferService->complete(
            $request->user(),
            $this->transfers->findOrFail($id),
            $options['valuation'] ?? 'cost',
            $options['record_branch_charge'] ?? true,
        ));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $this->transferService->cancel($request->user(), $this->transfers->findOrFail($id));

        return (new MessageResource(['message' => 'Cancelled.']))->response();
    }
}
