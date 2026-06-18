<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchFinance\IndexBranchFinanceRequest;
use App\Http\Requests\Api\V1\BranchFinance\StoreBranchChargeRequest;
use App\Http\Requests\Api\V1\BranchFinance\StoreBranchPaymentRequest;
use App\Http\Requests\Api\V1\BranchFinance\UpdateBranchFinancialEntryRequest;
use App\Http\Resources\BranchFinanceBalanceMatrixResource;
use App\Http\Resources\BranchFinancialEntryResource;
use App\Repositories\Contracts\BranchFinancialEntryRepositoryInterface;
use App\Services\BranchFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchFinanceController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private BranchFinancialEntryRepositoryInterface $entries,
        private BranchFinanceService $finance,
    ) {}

    public function balances(Request $request): JsonResponse
    {
        return response()->json([
            'balances' => BranchFinanceBalanceMatrixResource::collection(
                collect($this->finance->balanceMatrix($request->user()))
            ),
        ]);
    }

    public function index(IndexBranchFinanceRequest $request): AnonymousResourceCollection
    {
        return BranchFinancialEntryResource::collection(
            $this->entries->paginate($request->user(), $request->filters(), $request->perPage())
        );
    }

    public function show(string $id): BranchFinancialEntryResource
    {
        return new BranchFinancialEntryResource($this->resolveOrFail($this->entries->find($id)));
    }

    public function storeCharge(StoreBranchChargeRequest $request): JsonResponse
    {
        $entry = $this->finance->recordManualCharge($request->user(), $request->validated());

        return (new BranchFinancialEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function storePayment(StoreBranchPaymentRequest $request): JsonResponse
    {
        $entry = $this->finance->recordPayment($request->user(), $request->validated());

        return (new BranchFinancialEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function settle(Request $request, string $id): BranchFinancialEntryResource
    {
        $entry = $this->resolveOrFail($this->entries->find($id));

        return new BranchFinancialEntryResource(
            $this->finance->settleCharge($request->user(), $entry)
        );
    }

    public function update(UpdateBranchFinancialEntryRequest $request, string $id): BranchFinancialEntryResource
    {
        $entry = $this->resolveOrFail($this->entries->find($id));

        return new BranchFinancialEntryResource(
            $this->finance->updateEntry($request->user(), $entry, $request->validated())
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $entry = $this->resolveOrFail($this->entries->find($id));
        $this->finance->voidEntry($request->user(), $entry);

        return response()->json(null, 204);
    }
}
