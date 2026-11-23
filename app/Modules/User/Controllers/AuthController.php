<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Requests\LoginRequest;
use App\Modules\User\Requests\RegisterRequest;
use App\Modules\User\Requests\UpdateProfileRequest;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

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

        if (! $request->user()->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini telah dinonaktifkan.',
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
     * The first account in the system becomes the superadmin / developer.
     * Every later account starts as a read-only viewer until an admin changes its role.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            // Ensure the developer role exists in the database before assigning (Spatie).
            $isFirst = User::query()->count() === 1;
            $role = $isFirst ? Role::Developer->value : Role::Viewer->value;
            SpatieRole::findOrCreate($role, 'web');

            $user->assignRole($role);

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
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        return response()->json([
            'message' => 'Logged out.',
        ])->withCookie(
            Cookie::forget(config('session.cookie'))
        );
    }

    /**
     * Return the authenticated user with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user() ? new UserResource($request->user()) : null,
        ]);
    }

    /**
     * Update the authenticated user's own profile.
     *
     * Role and permissions cannot be changed here; admins use /api/users instead.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! empty($data['avatar_url'])) {
            $data['avatar_url'] = $this->storeAvatar($user, $data['avatar_url']);
        }

        $user->update($data);

        return response()->json([
            'user' => new UserResource($user->fresh('roles')),
        ]);
    }

    /**
     * Persist a base64 data-URL avatar to the public uploads directory.
     * Plain URLs are returned untouched.
     */
    private function storeAvatar(User $user, string $avatar): string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|gif);base64,(.+)$/s', $avatar, $matches)) {
            return $avatar;
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            return $avatar;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $directory = public_path('uploads/avatars');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = sprintf('avatar-%d.%s', $user->id, $extension);
        file_put_contents($directory.DIRECTORY_SEPARATOR.$fileName, $binary);

        return '/uploads/avatars/'.$fileName;
    }
}
