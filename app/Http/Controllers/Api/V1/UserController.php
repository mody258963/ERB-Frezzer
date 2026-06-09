<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesRepositoryModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    use ResolvesRepositoryModels;

    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'branch_id' => $request->query('branch_id'),
            'role' => $request->query('role'),
        ];

        return UserResource::collection(
            $this->users->paginate($filters, (int) $request->query('per_page', 25))
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user->load('branch')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): UserResource
    {
        return new UserResource($this->resolveOrFail($this->users->find($id)));
    }

    public function update(UpdateUserRequest $request, string $id): UserResource
    {
        $user = $this->resolveOrFail($this->users->find($id));
        $data = $request->validated();

        if (! $request->filled('password')) {
            unset($data['password']);
        }

        return new UserResource($this->users->update($user, $data));
    }

    public function destroy(string $id): JsonResponse
    {
        $user = $this->resolveOrFail($this->users->find($id));
        $this->users->update($user, ['is_active' => false]);

        return response()->json(null, 204);
    }
}
