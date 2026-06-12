<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CollectCustomerPaymentRequest;
use App\Http\Requests\Api\V1\Customer\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customer\OffsetSupplierRequest;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\ContraSettlementResource;
use App\Http\Resources\CustomerBalanceResource;
use App\Http\Resources\CustomerPaymentResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\LinkedPartyBalanceResource;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\ContraSettlementService;
use App\Services\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private CustomerRepositoryInterface $customers,
        private CustomerPaymentService $customerPayments,
        private ContraSettlementService $contraSettlements,
    ) {}

    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        return CustomerResource::collection(
            $this->customers->paginate($request->user(), $request->filters(), $request->perPage())
        );
    }

    public function show(string $id): CustomerResource
    {
        $customer = $this->resolveOrFail($this->customers->find($id));

        return new CustomerResource($customer->load('linkedSupplier'));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        return (new CustomerResource($this->customers->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCustomerRequest $request, string $id): CustomerResource
    {
        $customer = $this->resolveOrFail($this->customers->find($id));

        return new CustomerResource($this->customers->update($customer, $request->validated())->load('linkedSupplier'));
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = $this->resolveOrFail($this->customers->find($id));
        $this->customers->update($customer, ['is_active' => false]);

        return response()->json(null, 204);
    }

    public function invoices(string $id): AnonymousResourceCollection
    {
        $this->resolveOrFail($this->customers->find($id));

        return InvoiceResource::collection($this->customers->paginatedInvoices($id));
    }

    public function balance(string $id): CustomerBalanceResource
    {
        $customer = $this->resolveOrFail($this->customers->find($id));

        return new CustomerBalanceResource([
            'outstanding_balance' => (float) $customer->outstanding_balance,
            'unpaid_invoices' => $this->customers->unpaidCreditInvoices($id),
        ]);
    }

    public function collectPayment(CollectCustomerPaymentRequest $request, string $id): CustomerPaymentResource
    {
        $customer = $this->customers->findOrFail($id);

        return new CustomerPaymentResource(
            $this->customerPayments->collect($request->user(), $customer, $request->validated())
        );
    }

    public function payments(Request $request, string $id): AnonymousResourceCollection
    {
        $this->resolveOrFail($this->customers->find($id));

        return CustomerPaymentResource::collection(
            $this->customerPayments->history($id, (int) $request->query('per_page', 25))
        );
    }

    public function linkedBalance(string $id): LinkedPartyBalanceResource
    {
        $customer = $this->customers->findOrFail($id)->load('linkedSupplier');

        return new LinkedPartyBalanceResource($this->contraSettlements->netBalance($customer));
    }

    public function offsetSupplier(OffsetSupplierRequest $request, string $id): JsonResponse
    {
        $customer = $this->customers->findOrFail($id);

        return (new ContraSettlementResource(
            $this->contraSettlements->offset($request->user(), $customer, $request->validated())
        ))->response()->setStatusCode(201);
    }

    public function contraSettlements(Request $request, string $id): AnonymousResourceCollection
    {
        $this->customers->findOrFail($id);

        return ContraSettlementResource::collection(
            $this->contraSettlements->history($id, (int) $request->query('per_page', 25))
        );
    }
}
