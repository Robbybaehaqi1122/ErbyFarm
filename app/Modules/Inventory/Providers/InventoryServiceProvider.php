<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Policies\InventoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
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
        Gate::policy(Inventory::class, InventoryPolicy::class);
    }
}
