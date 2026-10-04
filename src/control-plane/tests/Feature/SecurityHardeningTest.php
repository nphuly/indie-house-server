<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A running `npm run dev` would add its origin to the policy.
        Vite::useHotFile(storage_path('framework/testing/vite.hot'));
    }

    public function test_login_limits_count_per_visitor_address_forwarded_by_the_edge(): void
    {
        // Production trusts the edge, which sets X-Forwarded-For to the visitor.
        TrustProxies::at('*');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.9')
                ->postJson('/login', ['email' => "listener{$attempt}@example.test"])
                ->assertUnprocessable();
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.9')
            ->postJson('/login', ['email' => 'one-more@example.test'])
            ->assertStatus(429);
        $this->withHeader('X-Forwarded-For', '198.51.100.7')
            ->postJson('/login', ['email' => 'one-more@example.test'])
            ->assertUnprocessable();
    }

    public function test_mobile_login_caps_attempts_per_address_across_accounts(): void
    {
        config(['stream.auth_rate_limit.store' => 'array']);
        TrustProxies::at('*');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.9')->postJson('/api/v1/mobile/auth/token', [
                'email' => "listener{$attempt}@example.test", 'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.9')->postJson('/api/v1/mobile/auth/token', [
            'email' => 'one-more@example.test', 'password' => 'wrong-password',
        ])->assertStatus(429);
        $this->withHeader('X-Forwarded-For', '198.51.100.7')->postJson('/api/v1/mobile/auth/token', [
            'email' => 'one-more@example.test', 'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_internal_api_refuses_every_key_while_none_is_configured(): void
    {
        config(['app.internal_api_key' => null]);

        $this->postJson('/api/internal/tracks/status', [], ['X-Internal-Key' => ''])->assertUnauthorized();
        $this->postJson('/api/internal/tracks/status', [], ['X-Internal-Key' => 'guess'])->assertUnauthorized();

        config(['app.internal_api_key' => '']);
        $this->postJson('/api/internal/tracks/status', [], ['X-Internal-Key' => ''])->assertUnauthorized();
    }

    public function test_a_session_signed_in_with_an_old_password_is_signed_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => 'hash-from-before-a-password-change'])
            ->get(route('library.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_the_password_keeps_the_current_session_signed_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);

        $this->actingAs($user)->get(route('library.index'))->assertOk();
        $this->actingAs($user)->put(route('settings.password'), [
            'current_password' => 'CurrentPassword123!',
            'password' => 'ChangedPassword456!',
            'password_confirmation' => 'ChangedPassword456!',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user->fresh())->get(route('library.index'))->assertOk();
    }

    public function test_signing_in_over_another_account_keeps_the_new_account_signed_in(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create(['password' => Hash::make('SecondPassword123!')]);

        // The Leap login form accepts a sign-in while someone is signed in.
        $this->actingAs($first)->get(route('legacy.index'))->assertOk();
        $this->post(route('legacy.login'), ['email' => $second->email, 'password' => 'SecondPassword123!'])
            ->assertRedirect(route('legacy.index'));

        $this->get(route('legacy.index'))->assertOk();
        $this->assertAuthenticatedAs($second);
    }

    public function test_admin_avatars_are_drawn_locally_instead_of_by_a_third_party(): void
    {
        Filament::setCurrentPanel('admin');

        $url = Filament::getUserAvatarUrl(User::factory()->make(['name' => 'ly nguyen']));

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringContainsString('>LN</text>', base64_decode(substr($url, strlen('data:image/svg+xml;base64,'))));
    }

    public function test_listener_pages_only_run_scripts_that_carry_the_request_nonce(): void
    {
        config(['filesystems.disks.s3.endpoint' => 'https://objects.example.test']);

        $response = $this->get(route('login'))->assertOk();
        $policy = $this->directives($response->headers->get('Content-Security-Policy'));

        $this->assertMatchesRegularExpression("/^'self' 'nonce-([A-Za-z0-9]+)'$/", $policy['script-src']);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy['script-src'], $nonce);
        $response->assertSee('nonce="'.$nonce[1].'"', false);
        $this->assertSame("'none'", $policy['object-src']);
        $this->assertSame("'self'", $policy['frame-ancestors']);
        $this->assertStringContainsString('https://objects.example.test', $policy['img-src']);
        $this->assertSame("'self'", $policy['media-src']);
    }

    public function test_the_admin_panel_allows_filament_inline_scripts_from_this_origin_only(): void
    {
        $policy = $this->directives($this->get('/admin/login')->assertOk()->headers->get('Content-Security-Policy'));

        $this->assertSame("'self' 'unsafe-inline' 'unsafe-eval'", $policy['script-src']);
        $this->assertSame("'self' blob:", $policy['worker-src']);
        $this->assertSame("'none'", $policy['object-src']);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_pages_cannot_be_framed_and_keep_their_own_referrer_policy(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->getJson('/.well-known/indie-house')->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        config(['instance.registration_mode' => 'invite_only']);
        $this->get(route('invite.show', str_repeat('a', 64)))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    /** @return array<string, string> */
    private function directives(?string $policy): array
    {
        return collect(explode(';', (string) $policy))
            ->map(fn (string $directive): array => explode(' ', trim($directive), 2) + [1 => ''])
            ->mapWithKeys(fn (array $parts): array => [$parts[0] => $parts[1]])
            ->all();
    }
}
