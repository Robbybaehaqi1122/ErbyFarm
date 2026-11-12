<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\Inventory;
use App\Modules\User\Models\User;

class InventoryPolicy
{
    /**
     * Determine whether the user can view any inventory items.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can create inventory items.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can view the inventory item.
     */
    public function view(User $user, Inventory $inventory): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can update the inventory item.
     */
    public function update(User $user, Inventory $inventory): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can delete the inventory item.
     */
    public function delete(User $user, Inventory $inventory): bool
    {
        return $user->hasRole(['admin']);
    }
}
