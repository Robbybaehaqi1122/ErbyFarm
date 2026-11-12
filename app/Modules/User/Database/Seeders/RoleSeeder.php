<?php

namespace App\Modules\User\Database\Seeders;

use App\Modules\User\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Create the base roles required by the MVP.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value, 'web');
        }
    }
}
