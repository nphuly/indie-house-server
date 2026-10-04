<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Private instance identity
    |--------------------------------------------------------------------------
    |
    | These values are public metadata exposed only through the private
    | overlay. INSTANCE_ID must be created once for an installation and backed
    | up with it; changing it makes a mobile client treat the host as a new
    | library.
    |
    */
    'id' => env('INSTANCE_ID'),
    'name' => env('INSTANCE_NAME', env('APP_NAME', 'Indie House')),
    'base_url' => rtrim((string) env('INSTANCE_BASE_URL', env('APP_URL', '')), '/'),
    'minimum_mobile_version' => env('INSTANCE_MINIMUM_MOBILE_VERSION', '1.0.0'),
    // Shown on /terms and /privacy for data requests and copyright complaints.
    'contact_email' => env('INSTANCE_CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Account provisioning
    |--------------------------------------------------------------------------
    |
    | Closed disables self-registration; invite_only allows only valid invites.
    | Open must be explicitly selected by the operator.
    |
    */
    'registration_mode' => env('INSTANCE_REGISTRATION_MODE', 'closed'),
    'max_users' => (int) env('INSTANCE_MAX_USERS', 100),
    'max_active_invites' => (int) env('INSTANCE_MAX_ACTIVE_INVITES', 20),
];
