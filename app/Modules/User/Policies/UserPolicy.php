<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;

class UserPolicy
{
    /**
     * Only admins may browse the user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    /**
     * Only admins may view other accounts.
     */
    public function view(User $user, User $target): bool
    {
        return $user->hasRole(Role::Admin->value) || $user->is($target);
    }

    /**
     * Only admins may change roles.
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    /**
     * Admins may delete other accounts, never themselves.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasRole(Role::Admin->value) && ! $user->is($target);
    }
}
