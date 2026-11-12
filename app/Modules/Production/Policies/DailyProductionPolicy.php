<?php

namespace App\Modules\Production\Policies;

use App\Modules\Production\Models\DailyProduction;
use App\Modules\User\Models\User;

class DailyProductionPolicy
{
    /**
     * Determine whether the user can view any daily production records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can create daily production records.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can view the daily production record.
     */
    public function view(User $user, DailyProduction $dailyProduction): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can update the daily production record.
     */
    public function update(User $user, DailyProduction $dailyProduction): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can delete the daily production record.
     */
    public function delete(User $user, DailyProduction $dailyProduction): bool
    {
        return $user->hasRole(['admin']);
    }
}
