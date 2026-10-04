<?php

namespace App\Console\Commands;

use App\Enums\Roles;
use App\Models\Auth\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    protected $signature = 'user:create-super-admin
        {--name= : Full name of the account owner}
        {--email= : Email address of the account owner}
        {--password= : Password (prefer the interactive prompt so it is not stored in shell history)}
        {--force : Update an existing account with the supplied email without confirmation}';

    protected $description = 'Create or promote a user account to super_admin';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Full name');
        $email = $this->option('email') ?? $this->ask('Email address');
        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret('Password');
            $passwordConfirmation = $this->secret('Confirm password');
        } else {
            $passwordConfirmation = $password;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $this->option('force') && ! $this->confirm("An account already exists for {$email}. Update it and grant super_admin?")) {
            $this->warn('No changes were made.');

            return self::FAILURE;
        }

        $wasCreated = $user === null;
        $user ??= new User;
        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->save();

        $superAdminRole = Role::findOrCreate(Roles::SUPER_ADMIN->value, 'web');
        $user->assignRole($superAdminRole);

        $this->info(sprintf(
            '%s %s and granted the %s role.',
            $wasCreated ? 'Created' : 'Updated',
            $user->email,
            Roles::SUPER_ADMIN->value,
        ));

        return self::SUCCESS;
    }
}
