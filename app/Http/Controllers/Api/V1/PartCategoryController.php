<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartCategoryResource;
use App\Models\PartCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:part_categories,key'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

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

    public function update(Request $request, string $id): PartCategoryResource
    {
        $category = PartCategory::query()->findOrFail($id);

        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('part_categories', 'key')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $category->update($data);

        return new PartCategoryResource($category->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $category = PartCategory::query()->findOrFail($id);
        $category->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
