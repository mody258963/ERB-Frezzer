<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Branch\StoreBranchRequest;
use App\Http\Requests\Api\V1\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private BranchRepositoryInterface $branches
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return BranchResource::collection(
            $this->branches->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function active(Request $request): AnonymousResourceCollection
    {
        return BranchResource::collection($this->branches->allActive($request->user()));
    }

    public function show(string $id): BranchResource
    {
        return new BranchResource($this->resolveOrFail($this->branches->find($id)));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        return (new BranchResource($this->branches->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBranchRequest $request, string $id): BranchResource
    {
        $branch = $this->resolveOrFail($this->branches->find($id));

        return new BranchResource($this->branches->update($branch, $request->validated()));
    }

    public function destroy(string $id): JsonResponse
    {
        $branch = $this->resolveOrFail($this->branches->find($id));
        $branch->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
