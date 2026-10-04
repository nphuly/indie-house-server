<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLoginRateLimitTest extends TestCase
{
    public function test_public_login_limits_attempts_per_account_without_blocking_another_account(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', [
                'email' => 'listener@example.test',
            ])->assertUnprocessable();
        }

        $this->postJson('/login', [
            'email' => 'listener@example.test',
        ])->assertStatus(429);

        $this->postJson('/login', [
            'email' => 'another@example.test',
        ])->assertUnprocessable();
    }
}
