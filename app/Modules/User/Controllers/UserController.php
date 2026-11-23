<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\User;
use App\Modules\User\Requests\StoreUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Requests\UpdateUserRoleRequest;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role as SpatieRole;

class UserController extends Controller
{
    /**
     * Paginate users with their assigned roles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Show a single user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        // Ensure the role exists in the database before assigning (Spatie).
        SpatieRole::findOrCreate($request->validated('role'), 'web');

        $user->assignRole($request->validated('role'));

        return response()->json([
            'user' => new UserResource($user->load('roles')),
        ], 201);
    }

    /**
     * Update user profile and account details.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $accountFields = [
            'name',
            'email',
            'organization',
            'phone',
            'address',
            'state',
            'zip_code',
            'country',
            'language',
            'timezone',
            'currency',
            'avatar_url',
            'is_active',
        ];

        $updateData = [];
        foreach ($accountFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $user->update($updateData);

        if (array_key_exists('role', $data)) {
            // Ensure the role exists in the database before syncing (Spatie).
            SpatieRole::findOrCreate($data['role'], 'web');
            $user->syncRoles([$data['role']]);
        }

        return response()->json([
            'user' => new UserResource($user->fresh('roles')),
        ]);
    }

    /**
     * Replace the user's role.
     */
    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        // Ensure the role exists in the database before syncing (Spatie).
        SpatieRole::findOrCreate($request->validated('role'), 'web');

        $user->syncRoles([$request->validated('role')]);

        return response()->json([
            'user' => new UserResource($user->fresh('roles')),
        ]);
    }

    /**
     * Delete a user. Admins cannot delete themselves through this endpoint.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');

        $user->delete();

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }
}
