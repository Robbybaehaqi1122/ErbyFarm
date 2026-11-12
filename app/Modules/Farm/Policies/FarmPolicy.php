<?php

namespace App\Modules\Farm\Policies;

use App\Modules\Farm\Models\Farm;
use App\Modules\User\Models\User;

class FarmPolicy
{
    /**
     * Determine whether the user can view any farms.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can create farms.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can view the farm.
     */
    public function view(User $user, Farm $farm): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can update the farm.
     */
    public function update(User $user, Farm $farm): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can delete the farm.
     */
    public function delete(User $user, Farm $farm): bool
    {
        return $user->hasRole(['admin']);
    }
}
