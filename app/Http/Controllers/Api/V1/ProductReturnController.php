<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReturnReferenceType;
use App\Enums\ReturnType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductReturnResource;
use App\Models\ProductReturn;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Services\ReturnQuantityValidator;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductReturnController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'return_type' => ['required', 'in:customer_return,supplier_return'],
            'reference_id' => ['required', 'uuid'],
            'reference_type' => ['required', 'in:invoice,purchase_order'],
            'customer_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string'],
            'attachment_url' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric'],
            'items.*.condition' => ['required', 'in:sellable,defective'],
        ]);

        $items = [];
        $totalValue = '0';
        foreach ($data['items'] as $row) {
            $lineTotal = bcmul((string) $row['unit_price'], (string) $row['quantity'], 2);
            $totalValue = bcadd($totalValue, $lineTotal, 2);
            $items[] = [
                'part_id' => $row['part_id'],
                'quantity' => $row['quantity'],
                'unit_price' => (string) $row['unit_price'],
                'condition' => $row['condition'],
                'total' => $lineTotal,
            ];
        }

        if ($data['return_type'] === ReturnType::CustomerReturn->value
            && $data['reference_type'] === ReturnReferenceType::Invoice->value) {
            $this->returnQuantities->assertCustomerInvoiceReturn($data['reference_id'], $items);
        }

        if ($data['return_type'] === ReturnType::SupplierReturn->value
            && $data['reference_type'] === ReturnReferenceType::PurchaseOrder->value) {
            $this->returnQuantities->assertSupplierPurchaseReturn($data['reference_id'], $items);
        }

        $ret = $this->returns->create(
            [
                'return_number' => $this->returns->nextReturnNumber(),
                'return_type' => $data['return_type'],
                'reference_id' => $data['reference_id'],
                'reference_type' => $data['reference_type'],
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'resolution' => null,
                'total_value' => $totalValue,
                'notes' => null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'approved_by' => null,
                'created_by' => $request->user()->id,
            ],
            $items
        );

        if ($data['reference_type'] === ReturnReferenceType::Invoice->value) {
            $this->returnQuantities->syncInvoiceReturnStatus($data['reference_id']);
        }

        return (new ProductReturnResource($ret->load(['items.part', 'customer', 'supplier', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): ProductReturnResource
    {
        $r = $this->returns->findWithItems($id);
        abort_if(! $r, 404);

        return new ProductReturnResource($r);
    }

    public function approve(Request $request, string $id): ProductReturnResource
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:restock,writeoff,replace,refund_cash,credit_note,supplier_credit'],
        ]);

        $r = ProductReturn::query()->findOrFail($id);

        return new ProductReturnResource(
            $this->returnService->approve($request->user(), $r, $data['resolution'])
                ->load(['items.part', 'customer', 'supplier', 'branch', 'creator', 'approver'])
        );
    }

    public function reject(Request $request, string $id): ProductReturnResource
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $r = ProductReturn::query()->findOrFail($id);

        return new ProductReturnResource($this->returnService->reject($request->user(), $r, $data['reason']));
    }
}
