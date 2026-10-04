<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Models\Auth\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_each_application_role_for_the_web_guard(): void
    {
        $this->seed(RoleSeeder::class);

        foreach (Roles::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_super_admin_command_creates_a_user_and_assigns_the_role(): void
    {
        $this->artisan('user:create-super-admin', [
            '--name' => 'Platform Owner',
            '--email' => 'owner@example.com',
            '--password' => 'a-secure-password',
            '--force' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('a-secure-password', $user->password));
        $this->assertTrue($user->hasRole(Roles::SUPER_ADMIN->value));
        $this->assertTrue(Gate::forUser($user)->allows('any-application-ability'));
        $this->assertSame('web', Role::findByName(Roles::SUPER_ADMIN->value)->guard_name);
    }
}
