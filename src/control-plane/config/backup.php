<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup encryption
    |--------------------------------------------------------------------------
    |
    | Every file inside a backup zip is encrypted with AES-256 under this key,
    | so the archive can travel offsite. Keep a copy outside the instance (a
    | password manager): without it no backup made with it can be restored.
    | Generate one with `openssl rand -base64 32`.
    |
    */
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
];
