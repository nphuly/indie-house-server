# Indie House documentation

**English** · [Tiếng Việt](../vi/README.md)

Indie House is a self-hosted, invite-only music streaming service for a small group of people. One person (the *operator*) runs an instance on their own server, uploads music they have the rights to, and invites friends. Listeners use the web app, the [iOS app](https://github.com/nphuly/indie-house-ios) or the [Android app for the BlackBerry Leap](https://github.com/nphuly/indie-house-android).

## Start here

| If you want to… | Read |
| --- | --- |
| Understand how the system fits together | [Architecture](architecture.md) |
| Run it on your machine and change the code | [Local development](local-development.md) |
| Host your own instance | [Deployment](deployment.md), then [Configuration](configuration.md) |
| Run an instance day to day | [Operations](operations.md) and [Backup and restore](backup-and-restore.md) |
| Understand how audio reaches the listener | [Playback and streaming](playback.md) |
| Build a client against the API | [Mobile API](mobile-api.md) |
| Review the security model | [Security](security.md) |
| Know what data an instance keeps | [Privacy and legal pages](privacy.md) |
| Work on the interface | [Design system](design-system.md) |

## What an instance offers

**Listeners**

- Browse the catalogue, artists and albums, and search without diacritics or with small typos.
- Build playlists and see their listening history.
- Choose the streaming quality: MP3 128 kbps, MP3 320 kbps or, when the operator grants it, lossless FLAC.
- Use the interface in Vietnamese or English, on the web, on iOS or on Android 4.3.
- Manage their profile, password, signed-in devices and data export from the settings page.

Visitors who are not signed in can browse and hear a 30-second preview of each track.

**Operators**

- An admin panel (`/admin`) for artists, albums and tracks, invites, user roles, streaming permissions, mobile sessions, JWT signing keys and backups.
- Registration modes: closed, invite-only or open.
- Encrypted, scheduled backups with an optional offsite copy.
- A health command and a monitoring script for alerting.

## Repository layout

```text
src/control-plane/    Laravel app: web UI, admin, mobile API, internal API, jobs
src/media-worker/     Go service that transcodes uploads with FFmpeg
src/stream-service/   Go audio gateway
infrastructure/       Caddy edge config, deploy and monitoring scripts, key folders
scripts/              Asset generators (icons, hero image) for the web and both apps
docker-compose*.yml   Shared, development and production Compose files
```

## Conventions in these docs

- *Instance* means one installation: its database, its object storage bucket, its signing keys and its users.
- Commands that start with `docker compose` assume you are in the repository root. In production they assume `COMPOSE_FILE` and `COMPOSE_PROFILES` are set in `.env` (see [Deployment](deployment.md)).
- `example.com` stands for your instance's public hostname.
