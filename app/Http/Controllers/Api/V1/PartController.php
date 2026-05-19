<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartAnalysisResource;
use App\Http\Resources\PartResource;
use App\Repositories\Contracts\PartRepositoryInterface;
use App\Services\PartAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    public function __construct(
        private PartRepositoryInterface $parts,
        private PartAnalysisService $partAnalysis,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'category' => $request->query('category'),
            'search' => $request->query('search'),
            'low_stock' => $request->boolean('low_stock'),
        ];

        return PartResource::collection(
            $this->parts->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function show(string $id): PartResource
    {
        $p = $this->parts->find($id);
        abort_if(! $p, 404);

        return new PartResource($p);
    }

    public function analysis(Request $request, string $id): PartAnalysisResource
    {
        $p = $this->parts->find($id);
        abort_if(! $p, 404);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
        ]);

        return new PartAnalysisResource(
            $this->partAnalysis->analyze(
                $p,
                $request->user(),
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['branch_id'] ?? null,
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:parts,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'unit' => ['required', 'string', 'max:32'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        return (new PartResource($this->parts->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): PartResource
    {
        $p = $this->parts->find($id);
        abort_if(! $p, 404);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', 'unique:parts,code,'.$id],
            'name' => ['sometimes', 'string'],
            'category' => ['sometimes', 'string'],
            'unit' => ['sometimes', 'string'],
            'sell_price' => ['sometimes', 'numeric'],
            'cost_price' => ['sometimes', 'numeric'],
            'min_stock' => ['sometimes', 'integer'],
            'is_active' => ['boolean'],
        ]);

        return new PartResource($this->parts->update($p, $data));
    }

    public function destroy(string $id): JsonResponse
    {
        $p = $this->parts->find($id);
        abort_if(! $p, 404);
        $this->parts->update($p, ['is_active' => false]);

        return response()->json(null, 204);
    }
}
