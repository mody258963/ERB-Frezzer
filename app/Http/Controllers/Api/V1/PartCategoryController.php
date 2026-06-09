<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartCategory\StorePartCategoryRequest;
use App\Http\Requests\Api\V1\PartCategory\UpdatePartCategoryRequest;
use App\Http\Resources\PartCategoryResource;
use App\Models\PartCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PartCategory::query()->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return PartCategoryResource::collection($query->get());
    }

    public function store(StorePartCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = PartCategory::query()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return (new PartCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePartCategoryRequest $request, string $id): PartCategoryResource
    {
        $category = PartCategory::query()->findOrFail($id);
        $category->update($request->validated());

        return new PartCategoryResource($category->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $category = PartCategory::query()->findOrFail($id);
        $category->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
