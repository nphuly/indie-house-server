<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'vi', 'app.supported_locales' => ['vi', 'en'], 'instance.name' => 'Nhà Nhạc']);
    }

    public function test_guests_can_read_the_terms_and_privacy_policy_in_both_languages(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Điều khoản sử dụng')
            ->assertSee('Nhà Nhạc không bán gói nghe')
            ->assertSee('không dành cho mục đích thương mại')
            ->assertDontSee(':instance')
            ->assertSee(route('legal.privacy'), false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Chính sách quyền riêng tư')
            ->assertSee('không được dùng vào bất kỳ mục đích thương mại nào')
            ->assertDontSee(':instance');

        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy policy')
            ->assertSee('never sold, rented, traded or used for advertising');
    }

    public function test_the_contact_section_shows_a_valid_operator_email_only(): void
    {
        $this->get(route('legal.terms'))
            ->assertSee('người đã mời bạn tham gia')
            ->assertDontSee('mailto:', false);

        config(['instance.contact_email' => 'not an email']);
        $this->get(route('legal.terms'))->assertDontSee('mailto:', false);

        config(['instance.contact_email' => 'operator@example.test']);
        $this->get(route('legal.privacy'))
            ->assertSee('href="mailto:operator@example.test"', false);
    }

    public function test_pages_that_create_an_account_link_to_the_terms(): void
    {
        config(['instance.registration_mode' => 'open']);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('href="'.route('legal.privacy').'"', false);

        config(['instance.registration_mode' => 'invite_only']);
        $this->get(route('invite.show', str_repeat('b', 64)))
            ->assertOk()
            ->assertSee('href="'.route('legal.terms').'"', false);

        $this->get(route('listen.home'))
            ->assertOk()
            ->assertSee('href="'.route('legal.privacy').'"', false);
    }
}
