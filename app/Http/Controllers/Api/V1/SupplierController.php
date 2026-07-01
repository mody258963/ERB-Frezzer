<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Supplier\CollectSupplierPaymentRequest;
use App\Http\Requests\Api\V1\Supplier\StoreSupplierRequest;
use App\Http\Requests\Api\V1\Supplier\UpdateSupplierRequest;
use App\Http\Resources\DashboardPayablesBySupplierResource;
use App\Http\Resources\LinkedPartyBalanceResource;
use App\Http\Resources\SupplierDebtResource;
use App\Http\Resources\SupplierInstallmentPaymentResource;
use App\Http\Resources\SupplierPaymentResource;
use App\Http\Resources\SupplierResource;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Services\ContraSettlementService;
use App\Services\DashboardQueryService;
use App\Services\SupplierPaymentService;
use App\Support\BranchVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private SupplierRepositoryInterface $suppliers,
        private ContraSettlementService $contraSettlements,
        private SupplierPaymentService $supplierPayments,
        private DashboardQueryService $dashboard,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            $this->suppliers->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function payablesBySupplier(Request $request): DashboardPayablesBySupplierResource
    {
        $branchId = BranchVisibility::activeBranchId($request->user());

        return new DashboardPayablesBySupplierResource($this->dashboard->payablesBySupplier($branchId));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        return (new SupplierResource($this->suppliers->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): SupplierResource
    {
        $supplier = $this->resolveOrFail($this->suppliers->find($id));

        return new SupplierResource($supplier->load('linkedCustomer'));
    }

    public function update(UpdateSupplierRequest $request, string $id): SupplierResource
    {
        $supplier = $this->resolveOrFail($this->suppliers->find($id));

        return new SupplierResource($this->suppliers->update($supplier, $request->validated()));
    }

    public function destroy(string $id): JsonResponse
    {
        $supplier = $this->resolveOrFail($this->suppliers->find($id));
        $this->suppliers->update($supplier, ['is_active' => false]);

        return response()->json(null, 204);
    }

    public function debt(string $id): SupplierDebtResource
    {
        return new SupplierDebtResource($this->suppliers->debtSnapshot($id));
    }

    public function linkedBalance(string $id): LinkedPartyBalanceResource
    {
        $supplier = $this->resolveOrFail($this->suppliers->find($id));

        return new LinkedPartyBalanceResource(
            $this->contraSettlements->netBalanceForSupplier($supplier->load('linkedCustomer'))
        );
    }

    public function collectPayment(CollectSupplierPaymentRequest $request, string $id): JsonResponse
    {
        $supplier = $this->resolveOrFail($this->suppliers->find($id));

        return (new SupplierPaymentResource(
            $this->supplierPayments->pay($request->user(), $supplier, $request->validated())
        ))->response()->setStatusCode(201);
    }

    public function payments(Request $request, string $id): AnonymousResourceCollection
    {
        $this->resolveOrFail($this->suppliers->find($id));

        return SupplierInstallmentPaymentResource::collection(
            $this->supplierPayments->history($id, (int) $request->query('per_page', 25))
        );
    }
}
