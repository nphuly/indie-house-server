<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstanceManifestTest extends TestCase
{
    public function test_it_exposes_the_instance_manifest_without_credentials(): void
    {
        config([
            'instance.id' => 'b87f1c21-767d-4b9b-8781-8b0eff8e5f1e',
            'instance.name' => 'Nhạc ở nhà Minh',
            'instance.base_url' => 'https://music.example.test',
            'instance.registration_mode' => 'closed',
            'instance.minimum_mobile_version' => '1.2.0',
            'stream.jwt.issuer' => 'https://music.example.test',
        ]);

        $this->getJson('/.well-known/indie-house')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertExactJson([
                'schema_version' => 1,
                'instance_id' => 'b87f1c21-767d-4b9b-8781-8b0eff8e5f1e',
                'display_name' => 'Nhạc ở nhà Minh',
                'api_base' => 'https://music.example.test/api/v1/mobile',
                'stream_base' => 'https://music.example.test/api/v1/stream',
                'jwt_issuer' => 'https://music.example.test',
                'registration_mode' => 'closed',
                'minimum_mobile_version' => '1.2.0',
            ]);
    }

    public function test_it_refuses_an_incomplete_or_inconsistent_instance_configuration(): void
    {
        config([
            'instance.id' => 'b87f1c21-767d-4b9b-8781-8b0eff8e5f1e',
            'instance.base_url' => 'https://music.example.test',
            'stream.jwt.issuer' => 'https://other.example.test',
        ]);

        $this->getJson('/.well-known/indie-house')
            ->assertServiceUnavailable()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertExactJson(['message' => __('mobile_api.manifest_not_configured')]);
    }
}
