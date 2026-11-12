<?php

namespace App\Modules\Farm\Providers;

use App\Modules\Farm\Models\Farm;
use App\Modules\Farm\Policies\FarmPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FarmServiceProvider extends ServiceProvider
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
        Gate::policy(Farm::class, FarmPolicy::class);
    }
}
