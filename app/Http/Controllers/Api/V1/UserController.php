<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
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

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedUserData($request, creating: true);

        $user = $this->users->create($data);

        return (new UserResource($user->load('branch')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): UserResource
    {
        $user = $this->users->find($id);
        abort_if(! $user, 404);

        return new UserResource($user);
    }

    public function update(Request $request, string $id): UserResource
    {
        $user = User::query()->findOrFail($id);
        $data = $this->validatedUserData($request, creating: false, user: $user);

        return new UserResource($this->users->update($user, $data));
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->users->update($user, ['is_active' => false]);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUserData(Request $request, bool $creating, ?User $user = null): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                $creating ? 'required' : 'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(UserRole::all())],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $data = $request->validate($rules);
        $role = $data['role'] ?? $user?->role->value;

        if (in_array($role, [UserRole::Salesperson->value, UserRole::Warehouse->value], true)
            && empty($data['branch_id'] ?? $user?->branch_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'branch_id' => ['Branch is required for salesperson and warehouse roles.'],
            ]);
        }

        if (! isset($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
