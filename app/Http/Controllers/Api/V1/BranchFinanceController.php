<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchFinanceBalanceMatrixResource;
use App\Http\Resources\BranchFinancialEntryResource;
use App\Repositories\Contracts\BranchFinancialEntryRepositoryInterface;
use App\Services\BranchFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchFinanceController extends Controller
{
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'creditor_branch_id' => ['nullable', 'uuid'],
            'debtor_branch_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:open,settled'],
            'entry_type' => ['nullable', 'in:charge,payment'],
        ]);

        return BranchFinancialEntryResource::collection(
            $this->entries->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function show(string $id): BranchFinancialEntryResource
    {
        $entry = $this->entries->find($id);
        abort_if(! $entry, 404);

        return new BranchFinancialEntryResource($entry);
    }

    public function storeCharge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creditor_branch_id' => ['required', 'uuid', 'different:debtor_branch_id'],
            'debtor_branch_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = $this->finance->recordManualCharge($request->user(), $data);

        return (new BranchFinancialEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creditor_branch_id' => ['required', 'uuid', 'different:debtor_branch_id'],
            'debtor_branch_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = $this->finance->recordPayment($request->user(), $data);

        return (new BranchFinancialEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function settle(Request $request, string $id): BranchFinancialEntryResource
    {
        $entry = $this->entries->find($id);
        abort_if(! $entry, 404);

        return new BranchFinancialEntryResource(
            $this->finance->settleCharge($request->user(), $entry)
        );
    }
}
