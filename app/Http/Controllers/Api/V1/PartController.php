<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Part\PartAnalysisRequest;
use App\Http\Requests\Api\V1\Part\StorePartImageRequest;
use App\Http\Requests\Api\V1\Part\StorePartRequest;
use App\Http\Requests\Api\V1\Part\UpdatePartRequest;
use App\Http\Resources\PartAnalysisResource;
use App\Http\Resources\PartResource;
use App\Repositories\Contracts\PartRepositoryInterface;
use App\Services\PartAnalysisService;
use App\Services\PartImageService;
use App\Support\PartLookupResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private PartRepositoryInterface $parts,
        private PartAnalysisService $partAnalysis,
        private PartImageService $partImages,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'category' => $request->query('category'),
            'category_id' => $request->query('category_id'),
            'search' => $request->query('search'),
            'low_stock' => $request->boolean('low_stock'),
        ];

        return PartResource::collection(
            $this->parts->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function show(string $id): PartResource
    {
        return new PartResource($this->resolveOrFail($this->parts->find($id)));
    }

    public function analysis(PartAnalysisRequest $request, string $id): PartAnalysisResource
    {
        $part = $this->resolveOrFail($this->parts->find($id));
        $filters = $request->validated();

        return new PartAnalysisResource(
            $this->partAnalysis->analyze(
                $part,
                $request->user(),
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['branch_id'] ?? null,
            )
        );
    }

    public function store(StorePartRequest $request): JsonResponse
    {
        $data = $request->validated();

        $part = $this->parts->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'category_id' => PartLookupResolver::resolveCategoryId($data),
            'unit' => $data['unit'],
            'sell_price' => $data['sell_price'],
            'cost_price' => $data['cost_price'],
            'min_stock' => $data['min_stock'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return (new PartResource($part->load(['category'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePartRequest $request, string $id): PartResource
    {
        $part = $this->resolveOrFail($this->parts->find($id));
        $data = $request->validated();

        if (isset($data['category_id']) || isset($data['category_key'])) {
            $data['category_id'] = PartLookupResolver::resolveCategoryId([
                'category_id' => $data['category_id'] ?? $part->category_id,
                'category_key' => $data['category_key'] ?? null,
            ]);
            unset($data['category_key']);
        }

        return new PartResource($this->parts->update($part, $data)->load(['category']));
    }

    public function destroy(string $id): JsonResponse
    {
        $part = $this->resolveOrFail($this->parts->find($id));
        $this->parts->update($part, ['is_active' => false]);

        return response()->json(null, 204);
    }

    public function storeImage(StorePartImageRequest $request, string $id): PartResource
    {
        $part = $this->resolveOrFail($this->parts->find($id));

        return new PartResource($this->partImages->store($part, $request->validated('image')));
    }

    public function destroyImage(string $id): PartResource
    {
        $part = $this->resolveOrFail($this->parts->find($id));

        return new PartResource($this->partImages->delete($part));
    }
}
