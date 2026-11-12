<?php

namespace App\Modules\User\Database\Seeders;

use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Create the default farm admin account.
     *
     * Development credentials: admin@erbyfarm.test / password
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@erbyfarm.test'],
            [
                'name' => 'Farm Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (! $admin->hasRole(Role::Admin->value)) {
            $admin->assignRole(Role::Admin->value);
        }
    }
}
