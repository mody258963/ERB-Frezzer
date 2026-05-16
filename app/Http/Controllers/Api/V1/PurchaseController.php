<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string'],
            'payment_type' => ['required', 'in:immediate,installments'],
            'installment_count' => ['nullable', 'integer', 'min:1'],
            'installment_start_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $po = $this->purchaseOrderService->create($request->user(), $data);

        return (new PurchaseOrderResource($po->load(['items.part', 'installments', 'supplier', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): PurchaseOrderResource
    {
        $po = $this->purchases->findWithRelations($id);
        abort_if(! $po, 404);

        return new PurchaseOrderResource($po);
    }

    public function receive(Request $request, string $id): PurchaseOrderResource
    {
        $po = PurchaseOrder::query()->findOrFail($id);

        return new PurchaseOrderResource(
            $this->purchaseOrderService->receive($request->user(), $po)->load(['items.part', 'installments', 'supplier', 'branch', 'creator'])
        );
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->purchaseOrderService->cancel($request->user(), $po);

        return (new MessageResource(['message' => 'Cancelled.']))->response();
    }
}
