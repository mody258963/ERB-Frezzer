<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockTransferController extends Controller
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private StockTransferService $transferService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StockTransferResource::collection(
            $this->transfers->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_branch_id' => ['required', 'uuid'],
            'to_branch_id' => ['required', 'uuid', 'different:from_branch_id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $transfer = $this->transfers->create(
            [
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ],
            $data['items']
        );

        return (new StockTransferResource($transfer))->response()->setStatusCode(201);
    }

    public function show(string $id): StockTransferResource
    {
        $t = $this->transfers->findWithItems($id);
        abort_if(! $t, 404);

        return new StockTransferResource($t);
    }

    public function complete(Request $request, string $id): StockTransferResource
    {
        $options = $request->validate([
            'valuation' => ['nullable', 'in:cost,sell'],
            'record_branch_charge' => ['nullable', 'boolean'],
        ]);

        $t = StockTransfer::query()->findOrFail($id);

        return new StockTransferResource($this->transferService->complete(
            $request->user(),
            $t,
            $options['valuation'] ?? 'cost',
            $options['record_branch_charge'] ?? true,
        ));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $t = StockTransfer::query()->findOrFail($id);
        $this->transferService->cancel($request->user(), $t);

        return (new MessageResource(['message' => 'Cancelled.']))->response();
    }
}
