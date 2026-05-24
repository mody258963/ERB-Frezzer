<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\MessageResource;
use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceService $invoiceService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'payment_type' => $request->query('payment_type'),
            'is_paid' => $request->query('is_paid'),
            'customer_id' => $request->query('customer_id'),
            'branch_id' => $request->query('branch_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        return InvoiceResource::collection(
            $this->invoices->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function pendingCredit(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->invoices->pendingCredit($request->user())->load(['customer', 'branch', 'items.part'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'payment_type' => ['required', 'in:credit,cash'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->create($request->user(), $data);

        return (new InvoiceResource($invoice->load(['items.part', 'customer', 'branch', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): InvoiceResource
    {
        $inv = $this->invoices->findWithItems($id);
        abort_if(! $inv, 404);

        return new InvoiceResource($inv);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $inv = Invoice::query()->findOrFail($id);
        $this->invoiceService->cancel($request->user(), $inv);

        return (new MessageResource(['message' => 'Invoice cancelled.']))->response();
    }
}
