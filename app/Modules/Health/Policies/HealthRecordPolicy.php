<?php

namespace App\Modules\Health\Policies;

use App\Modules\Health\Models\HealthRecord;
use App\Modules\User\Models\User;

class HealthRecordPolicy
{
    /**
     * Determine whether the user can view any health records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can create health records.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can view the health record.
     */
    public function view(User $user, HealthRecord $healthRecord): bool
    {
        return $user->hasRole(['admin', 'operator', 'viewer']);
    }

    /**
     * Determine whether the user can update the health record.
     */
    public function update(User $user, HealthRecord $healthRecord): bool
    {
        return $user->hasRole(['admin', 'operator']);
    }

    /**
     * Determine whether the user can delete the health record.
     */
    public function delete(User $user, HealthRecord $healthRecord): bool
    {
        return $user->hasRole(['admin']);
    }
}
