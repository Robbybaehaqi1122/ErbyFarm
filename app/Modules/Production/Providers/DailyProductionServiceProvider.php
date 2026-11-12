<?php

namespace App\Modules\Production\Providers;

use App\Modules\Production\Models\DailyProduction;
use App\Modules\Production\Policies\DailyProductionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DailyProductionServiceProvider extends ServiceProvider
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
        Gate::policy(DailyProduction::class, DailyProductionPolicy::class);
    }
}
