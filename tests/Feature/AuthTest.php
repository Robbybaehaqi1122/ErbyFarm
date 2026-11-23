<?php

namespace Tests\Feature;

use App\Modules\User\Database\Seeders\RoleSeeder;
use App\Modules\User\Enums\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Make Sanctum treat the request as a stateful same-origin SPA request.
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_first_registered_user_becomes_developer(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'First Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'owner@example.com')
            ->assertJsonPath('user.roles.0', Role::Developer->value);

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
    }

    public function test_later_registrations_become_viewers(): void
    {
        User::factory()->create();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Farm Staff',
            'email' => 'staff@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.roles.0', Role::Viewer->value);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);
        $user->assignRole(Role::Operator->value);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'operator@example.com')
            ->assertJsonPath('user.roles.0', Role::Operator->value);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'operator@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Viewer->value);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.roles.0', Role::Viewer->value);
    }

    public function test_viewer_cannot_list_users(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(Role::Viewer->value);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_admin_can_list_and_change_user_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $operator = User::factory()->create();
        $operator->assignRole(Role::Viewer->value);

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->patchJson("/api/users/{$operator->id}/role", [
            'role' => Role::Operator->value,
        ])->assertOk()
            ->assertJsonPath('user.roles.0', Role::Operator->value);

        $this->assertTrue($operator->fresh()->hasRole(Role::Operator->value));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertUnprocessable();
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Viewer->value);

        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'Erby User',
            'organization' => 'Erby Lintas Inovasi',
            'phone' => '085117511220',
            'address' => 'Subang, Blanakan',
            'zip_code' => '41259',
            'country' => 'Indonesia',
            'language' => 'Indonesian',
            'timezone' => '(GMT+07:00) Jakarta',
            'currency' => 'IDR',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Erby User')
            ->assertJsonPath('user.organization', 'Erby Lintas Inovasi')
            ->assertJsonPath('user.currency', 'IDR');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Erby User',
            'organization' => 'Erby Lintas Inovasi',
        ]);
    }

    public function test_user_cannot_change_own_role_through_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Viewer->value);

        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'role' => Role::Admin->value,
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasRole(Role::Viewer->value));
        $this->assertFalse($user->fresh()->hasRole(Role::Admin->value));
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
