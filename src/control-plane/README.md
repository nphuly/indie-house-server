# control-plane

The Laravel application at the centre of [Indie House](../../README.md): the listener web app, the Filament admin panel (`/admin`), the mobile JSON API, the internal API used by the Go services, and the queue and scheduled jobs.

| Path | Contents |
| --- | --- |
| `app/Http/Controllers/Listen` | Listener web app |
| `app/Http/Controllers/Mobile`, `Auth` | Mobile API and authentication |
| `app/Http/Controllers/Media` | Internal API for the media worker and the gateway |
| `app/Filament` | Admin panel |
| `app/Services/Media` | Playback renditions, playback tickets, gateway cache invalidation |
| `app/Domain/Backup` | Encrypted backups and restore |
| `resources/views/listen`, `resources/css`, `resources/js` | Listener interface |
| `lang/vi`, `lang/en` | Translations, including the terms and privacy texts |

```bash
composer test                                   # the test suite (in-memory SQLite)
php artisan test --filter=NameOfTest            # one test
vendor/bin/pint                                 # format
npm run dev                                     # Vite
```

See [Local development](../../docs/en/local-development.md) ([Tiếng Việt](../../docs/vi/local-development.md)) and [Architecture](../../docs/en/architecture.md).
