<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\Auth\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $user = User::query()->firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
        ]);
        $user->assignRole(Roles::USER->value);

        $this->call(ListenerDemoSeeder::class);
    }
}
