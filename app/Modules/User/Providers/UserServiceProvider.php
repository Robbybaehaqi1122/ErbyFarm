<?php

namespace App\Modules\User\Providers;

use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Register policies and route middleware owned by this module.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }
}
