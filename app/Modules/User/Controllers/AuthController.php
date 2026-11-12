<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Requests\LoginRequest;
use App\Modules\User\Requests\RegisterRequest;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user using a same-origin session cookie.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Register a new account.
     *
     * The first account in the system becomes the farm admin. Every later
     * account starts as a read-only viewer until an admin changes its role.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            $user->assignRole(User::query()->count() === 1 ? Role::Admin->value : Role::Viewer->value);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * End the current session and revoke the current API token if present.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return the authenticated user with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
