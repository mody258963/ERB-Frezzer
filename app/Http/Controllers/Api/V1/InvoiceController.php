<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Api\V1\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceReceiptResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\MessageResource;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Services\InvoiceReturnContextService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceService $invoiceService,
        private InvoiceReturnContextService $invoiceReturnContext,
    ) {}

    public function index(IndexInvoiceRequest $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->invoices->paginate($request->user(), $request->filters(), $request->perPage())
        );
    }

    public function pendingCredit(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->invoices->pendingCredit($request->user())->load(['customer', 'branch', 'items.part'])
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->user(), $request->validated());

        return (new InvoiceResource($invoice->load(['items.part', 'customer', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): InvoiceResource
    {
        $invoice = $this->resolveOrFail($this->invoices->findWithItems($id));

        return (new InvoiceResource($invoice))
            ->withReturnContext($this->invoiceReturnContext->quantitiesByPart($invoice));
    }

    public function receipt(string $id): InvoiceReceiptResource
    {
        $invoice = $this->resolveOrFail($this->invoices->findWithItems($id));

        return new InvoiceReceiptResource($this->invoiceReturnContext->receiptPayload($invoice));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $this->invoiceService->cancel($request->user(), $this->invoices->findOrFail($id));

        return (new MessageResource(['message' => 'Invoice cancelled.']))->response();
    }
}
