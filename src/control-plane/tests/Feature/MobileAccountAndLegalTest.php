<?php

namespace Tests\Feature;

use App\Jobs\DeleteS3ObjectsJob;
use App\Models\Auth\User;
use App\Services\Auth\MobileTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAccountAndLegalTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==';

    private ?string $keyPath = null;

    protected function tearDown(): void
    {
        if ($this->keyPath && is_file($this->keyPath)) {
            unlink($this->keyPath);
        }
        parent::tearDown();
    }

    public function test_the_account_shows_the_users_own_profile_picture(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['name' => 'Ly', 'email' => 'ly@example.test']);
        $headers = $this->bearer($user);

        $this->getJson('/api/v1/mobile/account')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/account', $headers)
            ->assertOk()
            ->assertExactJson(['data' => ['name' => 'Ly', 'email' => 'ly@example.test', 'avatar_url' => null]]);

        Storage::disk('s3')->put("avatars/{$user->id}/portrait.png", base64_decode(self::PNG));
        $user->forceFill(['avatar_path' => "avatars/{$user->id}/portrait.png"])->save();

        $url = $this->getJson('/api/v1/mobile/account', $headers)->assertOk()->json('data.avatar_url');
        $this->assertMatchesRegularExpression('#^'.preg_quote(url('/api/v1/mobile/account/avatar'), '#').'/[0-9a-f]{12}$#', $url);

        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path)->assertUnauthorized();
        $this->get($path, $headers)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/api/v1/mobile/account/avatar/not-a-version', $headers)->assertNotFound();

        // Another account has no picture, whatever version it asks for.
        $this->get($path, $this->bearer(User::factory()->create()))->assertNotFound();
    }

    public function test_deleting_an_account_removes_its_profile_picture(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => "avatars/{$user->id}/portrait.png"])->save();

        $user->delete();

        Queue::assertPushed(DeleteS3ObjectsJob::class, fn (DeleteS3ObjectsJob $job): bool => $job->paths === ["avatars/{$user->id}/portrait.png"]);
    }

    public function test_the_apps_get_the_terms_and_privacy_policy_in_their_language(): void
    {
        config(['instance.name' => 'Nhà Nhạc', 'instance.contact_email' => 'owner@example.test']);

        $terms = $this->getJson('/api/v1/mobile/legal/terms', ['Accept-Language' => 'en'])->assertOk();
        $terms->assertJsonPath('data.document', 'terms')
            ->assertJsonPath('data.title', 'Terms of use')
            ->assertJsonPath('data.contact.email', 'owner@example.test')
            ->assertJsonPath('data.url', route('legal.terms'));
        $this->assertStringContainsString('Nhà Nhạc', $terms->json('data.intro'));
        $this->assertStringNotContainsString(':instance', json_encode($terms->json('data'), JSON_UNESCAPED_UNICODE));

        $privacy = $this->getJson('/api/v1/mobile/legal/privacy', ['Accept-Language' => 'vi'])->assertOk();
        $privacy->assertJsonPath('data.title', 'Chính sách quyền riêng tư');
        $labelled = collect($privacy->json('data.sections'))->flatMap(fn (array $section): array => $section['items'])
            ->firstWhere('label', '!=', null);
        $this->assertNotNull($labelled);

        $this->getJson('/api/v1/mobile/legal/cookies')->assertNotFound();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        if (! $this->keyPath) {
            $this->keyPath = tempnam(sys_get_temp_dir(), 'indie-api-key-');
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $privateKey);
            file_put_contents($this->keyPath, $privateKey);
            config([
                'stream.jwt.private_key_path' => $this->keyPath,
                'stream.jwt.key_id' => 'stream-v1',
                'stream.jwt.issuer' => 'https://identity.indie-house.test',
                'stream.jwt.audience' => 'indie-house-mobile',
                'stream.auth_rate_limit.store' => 'array',
            ]);
        }

        return ['Authorization' => 'Bearer '.app(MobileTokenService::class)->issue($user)['access_token']];
    }
}
