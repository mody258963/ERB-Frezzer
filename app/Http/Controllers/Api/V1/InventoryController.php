<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LowStockAlertResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Services\InventoryService;
use App\Support\BranchVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        private StockRepositoryInterface $stock,
        private InventoryService $inventoryService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Stock::query()->with(['part', 'branch']);
        BranchVisibility::scope($request->user(), $query, 'branch_id');

        $query->when($request->query('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->query('part_id'), fn ($q, $id) => $q->where('part_id', $id));

        return StockResource::collection($query->paginate((int) $request->query('per_page', 50)));
    }

    public function lowStock(): AnonymousResourceCollection
    {
        return LowStockAlertResource::collection($this->stock->lowStockByBranch());
    }

    public function byBranch(Request $request, string $branchId): AnonymousResourceCollection
    {
        $query = Stock::query()->with('part')->where('branch_id', $branchId);
        BranchVisibility::scope($request->user(), $query, 'branch_id');

        return StockResource::collection($query->get());
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'part_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->inventoryService->adjust($request->user(), $data);

        return (new MessageResource(['message' => 'Stock adjusted.']))->response();
    }
}
