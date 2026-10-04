# Playback and streaming

**English** · [Tiếng Việt](../vi/playback.md)

The control plane decides who may hear which track at which quality. The Go gateway (`stream-service`) moves the bytes. Audio never passes through PHP in production, and clients never receive a storage key.

## Renditions

The media worker produces these files under `processed/{trackId}/`:

| Rendition | Format | Who gets it |
| --- | --- | --- |
| `preview_30` | MP3, 96 kbps, first 30 seconds | Visitors who are not signed in |
| `mp3_128` | MP3, 128 kbps constant bitrate | Signed-in listeners, "128 kbps" |
| `mp3_320` | MP3, 320 kbps constant bitrate | Signed-in listeners, "320 kbps" |
| `lossless` | FLAC, 16-bit / 44.1 kHz stereo | Listeners granted lossless, "Lossless" |

- MP3 renditions are true CBR. The worker never adds `-q:a`, which would switch LAME to variable bitrate.
- FLAC is made only when `ffprobe` reports a lossless source (FLAC, WAV/PCM, ALAC, WavPack, APE, TTA). An MP3 upload never gets a lossless rendition. Higher bit depths or sample rates are converted with the soxr resampler and triangular dither, and `metaflac` adds a seek point every 10 seconds.
- The preview is a separate encoded file, not a time limit on the full track. Until a track has a preview, visitors cannot play it.
- Older tracks may still have HLS renditions (`hls_128`, `hls_320`). They are kept, but new jobs no longer produce HLS.

## Quality choices

Listeners choose in **Settings** (web) or the app settings. The API values are:

| Choice | API value | Plays | If missing |
| --- | --- | --- | --- |
| 128 kbps | `standard` (also the legacy `auto`) | `mp3_128` | `mp3_320` |
| 320 kbps | `high` | `mp3_320` | `mp3_128` |
| Lossless | `lossless` | `lossless` | `mp3_320`, then `mp3_128` |

`lossless` is offered only to users with lossless access (see [Operations](operations.md#streaming-access)). If access is revoked, a saved `lossless` choice plays 320 kbps. If a device cannot decode FLAC, the client falls back to 320 kbps and says so once. `App\Services\Media\PlaybackRenditions` is the only place that maps a choice to a rendition.

## Web playback

The `<audio>` element cannot send an `Authorization` header, and mobile browsers only allow `play()` if the source is set during the tap. So the web player always uses a same-origin URL:

```text
<audio src="/tracks/{publicId}/audio">
  → control plane: check the session, pick the rendition, sign a playback ticket
  → 302 /api/v1/stream/v1/play/{ticket}   (Cache-Control: no-store)
  → gateway: verify the ticket, look up the object, stream it with Range support
```

A **playback ticket** is an RS256 JWT signed with the same key as mobile access tokens, with `token_use: playback`, one track (`trk`) and one quality (`q`). It lasts `STREAM_PLAYBACK_TICKET_TTL_MINUTES` (60 by default). The gateway accepts tickets only on `/v1/play/{ticket}`, and the mobile API rejects them. For visitors, the subject is `guest:` followed by an HMAC of the session ID, so rate limits apply per browser.

If a ticket expires mid-track (for example after a long pause), the next `Range` request fails. The player then requests the same-origin URL again, gets a new ticket, and resumes from the same position.

The lightweight `/legacy` page for old mobile browsers works the same way.

## App playback

The apps call the gateway directly with their access token:

| Endpoint (under `/api/v1/stream`) | Purpose |
| --- | --- |
| `GET /healthz` | Liveness, no authentication |
| `GET /v1/tracks/{publicId}/stream?quality=…` | Stream MP3 or FLAC through the gateway |
| `GET /v1/tracks/{publicId}/stream?quality=…&delivery=redirect` | `302` to a short-lived presigned URL |
| `GET /v1/tracks/{publicId}/presigned-url?quality=…` | JSON with a presigned URL, the quality actually served and its expiry |
| `GET /v1/tracks/{publicId}/hls/{asset}` | HLS playlists and segments of older tracks |
| `GET /v1/play/{ticket}` | Web playback ticket (not for apps) |

`quality` is `auto`, `standard`, `high` or `lossless`. A `lossless` request without the `lossless` claim in the token plays as `high`, so revoking access never stops the music mid-track. Public IDs must be 12 alphanumeric characters, and HLS assets are limited to playlist and segment file names, so these routes cannot read arbitrary keys.

## What the gateway does

- **Tokens.** Accepts only RS256 with a known `kid`, and checks `iss`, `aud`, `exp`, `nbf` and `token_use`. It holds public keys only.
- **Rate limits.** Fixed windows in Redis per user and route: 120 stream, 30 presign and 600 HLS requests per minute by default. If Redis is down, it refuses requests rather than letting them through.
- **Lookups.** On a cache miss it asks the control plane's internal API which object to serve for a track and quality. The answer includes the key, MIME type and effective quality, none of which reach the client.
- **Cache.** Lookups and object sizes are cached for `CATALOGUE_CACHE_TTL_SECONDS` (300). When a track's visibility or renditions change, the control plane publishes its public ID on the Redis channel `stream:invalidate` after the transaction commits, and the gateway drops those entries at once. On every (re)subscription it clears the whole cache, since messages may have been missed. An epoch counter stops a lookup that started before an invalidation from being cached after it.
- **Range.** Proxies a single byte range with `206`, `Content-Range` and `Accept-Ranges`, which seeking needs.
- **Presigned URLs.** Valid for 5 minutes by default (15 at most), with `Cache-Control: no-store`.
- **Access log.** One JSON line per request; see [Operations](operations.md#monitoring).

## Play history

- A client creates a `playback_id` when a track starts and logs at most one event for it: when it ends, when the listener skips, or when the page closes. A partial listen counts after 10 seconds; a short track played to the end always counts.
- The server ignores a repeated `(user, track, playback_id)` for two hours. A completed play increments the track's play count in the same transaction.
- Listeners can turn off history recording in Settings.
- History shows each track once, at its latest play.

## Limits of protection

If a device can play unencrypted audio, it receives that audio, and a determined listener can record it. Indie House does not use DRM. What it guarantees is narrower: the original upload is never exposed, storage stays private, every request is authorised and rate-limited, there is no download link, and visitors only ever receive the separate preview file.
