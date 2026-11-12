<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\User;
use App\Modules\User\Requests\UpdateUserRoleRequest;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

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
     * Replace the user's role.
     */
    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
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
