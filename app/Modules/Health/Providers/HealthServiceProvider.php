<?php

namespace App\Modules\Health\Providers;

use App\Modules\Health\Models\HealthRecord;
use App\Modules\Health\Policies\HealthRecordPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HealthServiceProvider extends ServiceProvider
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
        Gate::policy(HealthRecord::class, HealthRecordPolicy::class);
    }
}
