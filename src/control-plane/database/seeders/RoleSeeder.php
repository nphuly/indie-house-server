<?php

namespace Database\Seeders;

use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Roles::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
