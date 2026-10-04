<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'vi', 'app.supported_locales' => ['vi', 'en']]);
    }

    public function test_listener_can_switch_language_and_it_is_remembered(): void
    {
        $this->get(route('listen.home'))
            ->assertOk()
            ->assertSee('<html lang="vi">', false)
            ->assertSee('Trang chủ');

        $this->from(route('listen.catalogue'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('listen.catalogue'))
            ->assertPlainCookie(SetLocale::COOKIE, 'en')
            ->assertSessionHas('locale', 'en');

        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->get(route('listen.catalogue'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Browse');
    }

    public function test_background_switch_returns_json(): void
    {
        $this->postJson(route('locale.update'), ['locale' => 'en'])
            ->assertOk()
            ->assertExactJson(['locale' => 'en'])
            ->assertPlainCookie(SetLocale::COOKIE, 'en');
    }

    public function test_unsupported_languages_are_rejected_and_ignored(): void
    {
        $this->postJson(route('locale.update'), ['locale' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->withUnencryptedCookie(SetLocale::COOKIE, '../../etc')
            ->get(route('listen.home'))
            ->assertOk()
            ->assertSee('<html lang="vi">', false);
    }

    public function test_error_pages_follow_the_chosen_language(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee(trans('errors.404.title', [], 'en'));
    }

    public function test_mobile_api_messages_follow_accept_language(): void
    {
        config(['stream.auth_rate_limit.store' => 'array']);
        User::factory()->create(['email' => 'listener@example.test']);
        $credentials = ['email' => 'listener@example.test', 'password' => 'wrong-password'];

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->postJson('/api/v1/mobile/auth/token', $credentials)
            ->assertUnauthorized()
            ->assertExactJson(['message' => trans('mobile_api.invalid_credentials', [], 'en')]);

        $this->withHeader('Accept-Language', 'vi-VN')
            ->postJson('/api/v1/mobile/auth/token', $credentials)
            ->assertUnauthorized()
            ->assertExactJson(['message' => trans('mobile_api.invalid_credentials', [], 'vi')]);

        $this->withHeader('Accept-Language', 'fr-FR')
            ->postJson('/api/v1/mobile/auth/token', $credentials)
            ->assertUnauthorized()
            ->assertExactJson(['message' => trans('mobile_api.invalid_credentials', [], 'vi')]);
    }

    public function test_listener_language_files_mirror_each_other(): void
    {
        foreach (['listen', 'listen_client', 'auth', 'errors', 'settings', 'invite', 'legal'] as $group) {
            $vi = array_keys(Arr::dot(require lang_path("vi/{$group}.php")));
            $en = array_keys(Arr::dot(require lang_path("en/{$group}.php")));
            sort($vi);
            sort($en);

            $this->assertSame($vi, $en, "lang/vi/{$group}.php and lang/en/{$group}.php have different keys.");
        }
    }
}
