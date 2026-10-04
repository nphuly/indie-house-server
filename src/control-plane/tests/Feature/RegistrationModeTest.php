<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled_for_closed_instances(): void
    {
        config(['instance.registration_mode' => 'closed']);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registrationPayload())->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invite_only_mode_does_not_expose_public_registration(): void
    {
        config(['instance.registration_mode' => 'invite_only']);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registrationPayload())->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_can_only_be_enabled_explicitly(): void
    {
        config(['instance.registration_mode' => 'open']);
        Role::findOrCreate('user', 'web');

        $this->post('/register', $this->registrationPayload())
            ->assertRedirect(route('library.index'));

        $this->assertDatabaseHas('users', ['email' => 'friend@example.test']);
        $this->assertSame(1, User::query()->count());
    }

    /** @return array<string, string> */
    private function registrationPayload(): array
    {
        return [
            'name' => 'Friend',
            'email' => 'friend@example.test',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ];
    }
}
