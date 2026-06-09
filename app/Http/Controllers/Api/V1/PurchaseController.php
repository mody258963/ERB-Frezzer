<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchase\StorePurchaseRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private PurchaseOrderRepositoryInterface $purchases,
        private PurchaseOrderService $purchaseOrderService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'supplier_id' => $request->query('supplier_id'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        return PurchaseOrderResource::collection(
            $this->purchases->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $po = $this->purchaseOrderService->create($request->user(), $request->validated());

        return (new PurchaseOrderResource($po->load(['items.part', 'installments', 'supplier', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->resolveOrFail($this->purchases->findWithRelations($id)));
    }

    public function receive(Request $request, string $id): PurchaseOrderResource
    {
        $po = $this->purchaseOrderService->receive(
            $request->user(),
            $this->purchases->findOrFail($id)
        );

        return new PurchaseOrderResource(
            $po->load(['items.part', 'installments', 'supplier', 'branch', 'creator'])
        );
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $this->purchaseOrderService->cancel($request->user(), $this->purchases->findOrFail($id));

        return (new MessageResource(['message' => 'Cancelled.']))->response();
    }
}
