<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;

class UserPolicy
{
    private function isAdminOrDeveloper(User $user): bool
    {
        return $user->hasRole(Role::Admin->value) || $user->hasRole(Role::Developer->value);
    }

    /**
     * Only admins and developers may browse the user list.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrDeveloper($user);
    }

    /**
     * Only admins and developers may view other accounts.
     */
    public function view(User $user, User $target): bool
    {
        return $this->isAdminOrDeveloper($user) || $user->is($target);
    }

    /**
     * Only admins and developers may change roles.
     */
    public function update(User $user, User $target): bool
    {
        return $this->isAdminOrDeveloper($user);
    }

    /**
     * Admins and developers may delete other accounts, never themselves.
     */
    public function delete(User $user, User $target): bool
    {
        return $this->isAdminOrDeveloper($user) && ! $user->is($target);
    }
}
