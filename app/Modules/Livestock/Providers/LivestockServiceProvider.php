<?php

namespace App\Modules\Livestock\Providers;

use App\Modules\Livestock\Models\Livestock;
use App\Modules\Livestock\Policies\LivestockPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class LivestockServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Register policies for this module.
     */
    public function boot(): void
    {
        Gate::policy(Livestock::class, LivestockPolicy::class);
    }
}
