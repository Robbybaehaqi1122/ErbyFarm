<?php

namespace App\Modules\Livestock\Policies;

use App\Modules\Livestock\Models\Livestock;
use App\Modules\User\Models\User;

class LivestockPolicy
{
    /**
     * Determine whether the user can view any livestock records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can create livestock records.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can view the livestock record.
     */
    public function view(User $user, Livestock $livestock): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can update the livestock record.
     */
    public function update(User $user, Livestock $livestock): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can delete the livestock record.
     */
    public function delete(User $user, Livestock $livestock): bool
    {
        return $user->hasRole(['admin']);
    }
}
