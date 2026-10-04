<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile stream tokens
    |--------------------------------------------------------------------------
    |
    | The private key belongs to the control plane only. It must be injected
    | by the runtime secret manager and must never be committed to the repo.
    |
    */
    'jwt' => [
        'private_key_path' => env('STREAM_JWT_PRIVATE_KEY_PATH'),
        'key_id' => env('STREAM_JWT_KEY_ID', 'stream-v1'),
        'managed_keys_directory' => env('STREAM_JWT_MANAGED_KEYS_DIRECTORY', '/app/storage/keys'),
        'issuer' => env('STREAM_JWT_ISSUER'),
        'audience' => env('STREAM_JWT_AUDIENCE', 'indie-house-mobile'),
        'access_ttl_minutes' => (int) env('STREAM_JWT_ACCESS_TTL_MINUTES', 15),
    ],

    'access' => [
        // Existing users receive this only until an explicit admin policy is
        // created for them. It preserves the original rollout behaviour.
        'default_high_quality_enabled' => (bool) env('STREAM_DEFAULT_HQ_ENABLED', true),
    ],

    'auth_rate_limit' => [
        // Login throttling remains ephemeral and deliberately does not use the
        // application's database cache store.
        'store' => env('STREAM_AUTH_RATE_LIMIT_STORE', 'redis'),
    ],

    'refresh' => [
        'ttl_days' => (int) env('STREAM_REFRESH_TTL_DAYS', 30),
        'max_sessions_per_user' => (int) env('STREAM_MAX_SESSIONS_PER_USER', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web audio through the stream gateway
    |--------------------------------------------------------------------------
    |
    | Web and legacy audio URLs answer with a redirect to the gateway, carrying
    | a playback ticket for one track at one quality. Turn this off only where
    | stream-service is not running (the development stack without the
    | "stream" profile); Laravel then streams the bytes itself.
    |
    */
    'web_audio_via_gateway' => (bool) env('STREAM_WEB_AUDIO_VIA_GATEWAY', true),

    'playback_ticket_ttl_minutes' => (int) env('STREAM_PLAYBACK_TICKET_TTL_MINUTES', 60),

    // Publish track changes so the gateway drops its cached lookups at once.
    'cache_invalidation' => (bool) env('STREAM_CACHE_INVALIDATION', true),
];
