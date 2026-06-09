<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReturnReferenceType;
use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductReturn\ApproveProductReturnRequest;
use App\Http\Requests\Api\V1\ProductReturn\RejectProductReturnRequest;
use App\Http\Requests\Api\V1\ProductReturn\StoreProductReturnRequest;
use App\Http\Resources\ProductReturnResource;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Services\ReturnQuantityValidator;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductReturnController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private ProductReturnRepositoryInterface $returns,
        private ReturnService $returnService,
        private ReturnQuantityValidator $returnQuantities,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'return_type' => $request->query('return_type'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        return ProductReturnResource::collection(
            $this->returns->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function store(StoreProductReturnRequest $request): JsonResponse
    {
        $payload = $request->payload($this->returnQuantities);

        $ret = $this->returns->create(
            [
                'return_number' => $this->returns->nextReturnNumber(),
                'return_type' => $payload['header']['return_type'],
                'reference_id' => $payload['header']['reference_id'],
                'reference_type' => $payload['header']['reference_type'],
                'customer_id' => $payload['header']['customer_id'],
                'supplier_id' => $payload['header']['supplier_id'],
                'branch_id' => $payload['header']['branch_id'],
                'reason' => $payload['header']['reason'],
                'status' => 'pending',
                'resolution' => null,
                'total_value' => $payload['total_value'],
                'notes' => null,
                'attachment_url' => $payload['header']['attachment_url'],
                'approved_by' => null,
                'created_by' => $request->user()->id,
            ],
            $payload['items']
        );

        if ($payload['header']['reference_type'] === ReturnReferenceType::Invoice->value) {
            $this->returnQuantities->syncInvoiceReturnStatus($payload['header']['reference_id']);
        }

        return (new ProductReturnResource($ret->load(['items.part', 'customer', 'supplier', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): ProductReturnResource
    {
        return new ProductReturnResource($this->resolveOrFail($this->returns->findWithItems($id)));
    }

    public function approve(ApproveProductReturnRequest $request, string $id): ProductReturnResource
    {
        return new ProductReturnResource(
            $this->returnService
                ->approve($request->user(), $this->returns->findOrFail($id), $request->validated('resolution'))
                ->load(['items.part', 'customer', 'supplier', 'branch', 'creator', 'approver'])
        );
    }

    public function reject(RejectProductReturnRequest $request, string $id): ProductReturnResource
    {
        return new ProductReturnResource(
            $this->returnService->reject(
                $request->user(),
                $this->returns->findOrFail($id),
                $request->validated('reason')
            )
        );
    }
}
