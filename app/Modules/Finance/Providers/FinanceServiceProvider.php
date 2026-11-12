<?php

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Policies\ExpensePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
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
        Gate::policy(Expense::class, ExpensePolicy::class);
    }
}
