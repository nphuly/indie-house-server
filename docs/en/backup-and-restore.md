# Backup and restore

**English** · [Tiếng Việt](../vi/backup-and-restore.md)

An instance has four kinds of data. The built-in backup covers the first one and part of the second; the rest is up to the operator.

| Data | Where it lives | Covered by the built-in backup |
| --- | --- | --- |
| Accounts, catalogue metadata, playlists, history, sessions, settings | PostgreSQL | Yes |
| Files on the control plane's local disk | `storage/app` in the container | Yes |
| Uploads, renditions, artwork, avatars | Object storage bucket | **No**: copy the bucket yourself |
| `.env`, `INSTANCE_ID`, signing keys, tunnel configuration | The server | **No**: keep an encrypted copy elsewhere |

Losing the bucket loses the music. Losing the signing key forces every app to sign in again. Losing `BACKUP_ENCRYPTION_KEY` makes every backup made with it unreadable.

## Built-in backups

**System → Data backups** creates a backup on demand and lists past ones. **System → Backup settings** configures:

- a schedule (cron expression, default `0 2 * * *`) and a cleanup schedule (default `0 3 * * *`);
- retention: keep at most the last *N* backups (default 10) and nothing older than *D* days (default 30);
- an offsite disk: set it to `s3` to copy each finished archive to object storage.

The same can be run from the command line with `backup:run` and `backup:clean`.

Each backup is a zip archive containing `database.sql` (a PostgreSQL dump), `settings.json`, a manifest, and the files on the control plane's local disk. **Every file inside is encrypted with AES-256** using `BACKUP_ENCRYPTION_KEY`. The file names inside the archive are not encrypted, only their contents.

- Without the key, the backup fails rather than writing an unencrypted archive. `instance:health` reports a missing key as `backup_encryption`.
- Store the key in a password manager, outside the server.
- To open an archive by hand, use 7-Zip (`unzip` cannot read AES): `7z x -p"$BACKUP_ENCRYPTION_KEY" backup-….zip`, in a directory only you can read.
- When you change the key, keep the old one as long as backups made with it exist. Make a new backup right after the change.
- Archives made before encryption existed can still be restored, but they are plain zip files. Delete them, including offsite copies, once you have encrypted ones.

## A complete backup

For disaster recovery, take all of these from the same point in time:

1. **Database.** The built-in backup, or `pg_dump -Fc` for a custom-format dump. Check a dump without importing it: `pg_restore --list backup.dump`.
2. **Bucket.** Mirror it with an S3 tool (`mc mirror` or `aws s3 sync`) and compare object counts and total size between source and copy. Pause uploads while copying for a consistent snapshot.
3. **Secrets.** `.env`, `infrastructure/stream-keys/`, `infrastructure/stream-public-keys/` and your tunnel or proxy configuration. Encrypt the bundle (for example with `age`) before it leaves the server.

Record the instance ID, the hostname and SHA-256 checksums in a manifest stored next to the backup, without secrets in it. Keep at least one copy off the server, and never put dumps or keys in Git.

## Restore drill

Test a restore regularly, on an **empty staging instance**, never on production volumes:

1. Create an empty database and an empty bucket.
2. Restore the dump, mirror the media into the new bucket and decrypt the secrets into a directory with restricted permissions.
3. Give staging its own HTTPS hostname. If you keep the original `INSTANCE_ID`, make sure no real app connects to both instances.
4. Compare the number of users, tracks, playlists, renditions and objects. Check `/up`, `/api/v1/stream/healthz` and the manifest, sign in, refresh a token, play and seek a track, and revoke a session.
5. Write down how long it took, the checksums and anything you had to fix. Then delete staging.

## Restoring production

1. Put the instance in maintenance and take one last backup of the current state.
2. Restore the database, the bucket and the secrets from **one** snapshot.
3. Start the services in dependency order and run the same checks as the drill.
4. Reopen the instance.

Keep the pre-restore snapshot until everything has been checked. Never delete volumes to "roll back".

The admin panel can also restore one of its own backups. It imports the database dump, the local files and the settings with the current encryption key, and stops before changing anything if the key is wrong. It does not restore the bucket or the secrets.
