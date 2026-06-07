<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContraSettlementResource;
use App\Http\Resources\CustomerBalanceResource;
use App\Http\Resources\CustomerPaymentResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\LinkedPartyBalanceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\ContraSettlementService;
use App\Services\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        private CustomerPaymentService $customerPayments,
        private ContraSettlementService $contraSettlements,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'type' => $request->query('type'),
            'search' => $request->query('search'),
        ];

        return CustomerResource::collection(
            $this->customers->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function show(string $id): CustomerResource
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);

        return new CustomerResource($c->load('linkedSupplier'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'type' => ['required', 'in:credit,cash'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'linked_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
        ]);

        if (($data['type'] ?? '') === 'credit' && ! isset($data['credit_limit'])) {
            $data['credit_limit'] = 0;
        }

        if ($data['type'] === 'cash') {
            $data['credit_limit'] = 0;
            $data['outstanding_balance'] = 0;
        }

        return (new CustomerResource($this->customers->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): CustomerResource
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:credit,cash'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['sometimes', 'numeric'],
            'linked_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
        ]);

        return new CustomerResource($this->customers->update($c, $data)->load('linkedSupplier'));
    }

    public function destroy(string $id): JsonResponse
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);
        $this->customers->update($c, ['is_active' => false]);

        return response()->json(null, 204);
    }

    public function invoices(string $id): AnonymousResourceCollection
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);

        return InvoiceResource::collection(
            Invoice::query()->where('customer_id', $id)->with(['branch', 'items.part'])->latest()->paginate(50)
        );
    }

    public function balance(string $id): CustomerBalanceResource
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);

        $unpaid = Invoice::query()
            ->where('customer_id', $id)
            ->where('payment_type', 'credit')
            ->where('is_paid', false)
            ->with(['branch', 'items.part'])
            ->get();

        return new CustomerBalanceResource([
            'outstanding_balance' => (float) $c->outstanding_balance,
            'unpaid_invoices' => $unpaid,
        ]);
    }

    public function collectPayment(Request $request, string $id): CustomerPaymentResource
    {
        $customer = Customer::query()->findOrFail($id);

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,check'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return new CustomerPaymentResource(
            $this->customerPayments->collect($request->user(), $customer, $data)
        );
    }

    public function payments(Request $request, string $id): AnonymousResourceCollection
    {
        $c = $this->customers->find($id);
        abort_if(! $c, 404);

        return CustomerPaymentResource::collection(
            $this->customerPayments->history($id, (int) $request->query('per_page', 25))
        );
    }

    public function linkedBalance(string $id): LinkedPartyBalanceResource
    {
        $customer = Customer::query()->with('linkedSupplier')->findOrFail($id);

        return new LinkedPartyBalanceResource(
            $this->contraSettlements->netBalance($customer)
        );
    }

    public function offsetSupplier(Request $request, string $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return (new ContraSettlementResource(
            $this->contraSettlements->offset($request->user(), $customer, $data)
        ))->response()->setStatusCode(201);
    }

    public function contraSettlements(Request $request, string $id): AnonymousResourceCollection
    {
        Customer::query()->findOrFail($id);

        return ContraSettlementResource::collection(
            $this->contraSettlements->history($id, (int) $request->query('per_page', 25))
        );
    }
}
