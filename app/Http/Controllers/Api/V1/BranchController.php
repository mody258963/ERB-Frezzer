<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(
        private BranchRepositoryInterface $branches
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 25);

        return BranchResource::collection($this->branches->paginate($request->user(), $perPage));
    }

    public function active(Request $request): AnonymousResourceCollection
    {
        return BranchResource::collection($this->branches->allActive($request->user()));
    }

    public function show(string $id): BranchResource
    {
        $branch = $this->branches->find($id);
        abort_if(! $branch, 404);

        return new BranchResource($branch);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        return (new BranchResource($this->branches->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): BranchResource
    {
        $branch = $this->branches->find($id);
        abort_if(! $branch, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        return new BranchResource($this->branches->update($branch, $data));
    }

    public function destroy(string $id): JsonResponse
    {
        $branch = $this->branches->find($id);
        abort_if(! $branch, 404);

        $branch->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
