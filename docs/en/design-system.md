# Design system: "Biển hiệu"

**English** · [Tiếng Việt](../vi/design-system.md)

*Biển hiệu* means "shop sign". The interface takes its cue from Saigon at night: neon signs, a record shop down an alley, wet streets reflecting the lights. The web app, the iOS app and the Android app share one identity, defined by the tokens in `src/control-plane/resources/css/app.css`.

## Principles

- **Artwork comes first.** Dark, layered backgrounds; detail pages and the player take their colour from the artwork. No full-screen neon, no heavy glassmorphism, no purple-to-blue gradients.
- **Sign yellow is a signal, not decoration.** It marks only the Play button, playback progress, the track that is playing and selected states. Secondary buttons are light or outlined.
- **Cut corners instead of rounded ones.** Play buttons, pill buttons and artwork have clipped corners; artists and avatars are octagons; everything else is almost square.
- **Monospace for data.** Durations, counts, track numbers, eyebrows, hostnames and versions use JetBrains Mono; everything else uses Chakra Petch.
- **A familiar layout, an identity of its own.** Library on the left, player along the bottom, queue on the right (on phones: four tabs, a mini player and a full-screen player). No colours, fonts or logos from other services.
- **Every state is designed:** skeletons while loading, empty states that suggest a next step, errors with a retry, busy buttons with their own label.
- **Plain words.** Page titles say what the page is ("Discover", "Library", "Settings"). No slogans or metaphors, no numbered sections, no exclamation marks. Every string exists in Vietnamese and English.

## Colour

| Token | Value | Use |
| --- | --- | --- |
| `--ih-bg` | `#07090E` | App frame and player background |
| `--ih-surface` | `#0E1118` | Panels: library, content, queue |
| `--ih-surface-raised` | `#151A25` | Hovered cards, mobile mini player, raised cards |
| `--ih-surface-hover` | `#1D2432` | Menus, pressed states |
| `--ih-text` | `#EEF1F6` | Main text, light buttons |
| `--ih-text-soft` | `#BCC3D0` | Descriptions and readable metadata |
| `--ih-muted` | `#858EA0` | Metadata, eyebrows, inactive icons |
| `--ih-faint` | `#566074` | Placeholders, very minor notes |
| `--ih-accent` | `#F7E23B` | Sign yellow: Play, progress, active state |
| `--ih-accent-bright` | `#FFF07A` | Yellow button on hover or press |
| `--ih-ink` | `#0A0B0F` | Text and icons on yellow or light backgrounds |
| `--ih-pink` | `#FF3D81` | Karaoke pink: avatars, the "Most played" label |
| `--ih-cyan` | `#27E3EC` | Neon cyan: focus rings |
| `--ih-line` | `rgb(238 241 246 / 12%)` | Borders and dividers |
| `--ih-danger` | `#FF5A4F` | Destructive actions and errors |

Artwork placeholders use six tones chosen by `crc32(seed) % 6`, in the same order on every platform: yellow `#F7E23B`, pink `#FF3D81`, cyan `#27E3EC`, violet `#9D7CFF`, orange `#FF8A3D`, blue `#5B8CFF`.

## Type

- **Chakra Petch** (400–700) for all interface text, **JetBrains Mono** (400–500) for numbers and data. Both are under the SIL Open Font License, cover Vietnamese, and are self-hosted (the web build bundles them; the apps ship subsets).
- Headings use weight 700 with −0.01em letter spacing and a line height of at least 1.05, so Vietnamese diacritics never touch the line above. Long album and playlist titles step down in size.
- Eyebrows are 11px uppercase mono in `--ih-muted`.

## Shape and texture

| Token | Shape | Use |
| --- | --- | --- |
| `--ih-clip-art` | Top-right corner cut, up to 18px | Artwork |
| `--ih-clip-play` | Top-right and bottom-left cut, 28% of the side | Play button, empty-state icon tile |
| `--ih-clip-octagon` | All four corners cut, 22% | Artists, avatars |
| inside `.button` | Two 9px cuts, border redrawn with a gradient | Buttons, chips |

`clip-path` also clips outlines and shadows, so clipped elements draw their focus ring inside (negative `outline-offset`). Radii (`--ih-radius-*`) stay at 2–4px, and stacking uses the `--ih-z-*` scale.

**Hologram layer.** Loaded artwork gets a light overlay: 1px scanlines every 3px, a faint cyan glow in one corner and pink in the opposite one, and a thin pink edge on the left and cyan on the right. On hover a cyan-pink sheen sweeps across; artwork in the player repeats it slowly while music plays. Placeholders do not get the layer, and it must never dim, blur or recolour the image. `prefers-reduced-motion` turns the motion off.

## Brand

- The logo is the neon `ih` mark in `src/control-plane/public/images/indie-house-mark.svg`: square strokes in sign yellow with a bright core and a glow, no frame. The wordmark is "INDIE HOUSE" in Chakra Petch 700, uppercase, with 0.1em tracking. The mark needs a dark background.
- `python3 scripts/generate-brand-assets.py` renders the app icons, splash images and favicon from that SVG.
- Icons come from one set (`App\Support\Icon`: a 24px grid, one stroke width). `scripts/generate-legacy-android-assets.py` exports the same glyphs as PNGs for Android and template PDFs for iOS. No icon fonts or symbol characters.
- The hero image, a record shop in a Saigon alley at night, is drawn entirely in code by `scripts/generate-record-shop-hero.py`: no photos, stock images or third-party logos.

## Layout

```text
Desktop (≥ 1200px)
┌ logo ‹ › ─────── ⌂ [ Search songs, artists, albums ] ──────── VI|EN avatar ┐
├─ Library ──────┬──────────── content panel ──────────────────┬ Queue ──────┤
│ History        │ hero coloured from the artwork               │ (toggle)    │
│ playlists …    │ action bar · sticky header on scroll          │             │
├────────────────┴───────────────────────────────────────────────┴────────────┤
│ now playing │ ⤮ ⏮ ▶ ⏭ ⟲   0:42 ━━━━━━▮──── 3:12 │ queue · volume            │
└─────────────────────────────────────────────────────────────────────────────┘

Tablet (768–1199px): the library collapses to an 80px icon rail; the queue overlays the content.

Phone (< 768px)
┌ logo ───────────────── VI|EN avatar ┐
│ full-bleed content                  │
│ ┌ mini player (tap to expand) ───┐  │
└ Home · Search · Discover · Library ┘
```

Shelves scroll horizontally with ‹ › buttons when they overflow; grids wrap from six columns down to two, based on the section's width. The player always reserves space at the bottom so it never hides the last row.

## Key components

- **Play button:** a yellow block with two cut corners and an `ink` icon. Bound to a playing context, it turns into Pause.
- **Entity hero** (album, artist, playlist): 232px artwork over a blurred copy of itself with scanlines, a mono eyebrow, a large title and a short metadata line. Playlists show a four-cover mosaic when they have enough distinct covers.
- **Track row:** tap to play. The number becomes a play button on hover and a yellow equalizer for the current track, whose title turns yellow with a yellow bar on the left. The `⋯` menu adds to a playlist, creates one, goes to the album or artist, or removes the track from the owner's playlist.
- **Player:** artwork, linked title and artist, transport controls, a scrubber with a vertical thumb, queue, mute and volume. Visitors see a "30-second preview" label. On phones, a mini player with a thin progress line opens a full-screen sheet.
- **Empty states, toasts and error pages:** an icon, a short title saying what is missing, one action. Error pages (403, 404, 419, 429, 500, 503) share one layout.

## Player behaviour

Shared by the web, iOS and Android:

- **The queue is the whole list** the tapped track came from (album, playlist, search results, history…), starting at that track. Each list has a context label shown as "Playing from".
- **A list's Play button** pauses or resumes if that list is already playing, and otherwise plays it from the first track.
- **Shuffle** keeps the current track first and shuffles the rest; turning it off returns to the original order at the current track. The action-bar shuffle button starts from a random track.
- **Repeat** cycles off → all → one. With repeat off, the queue stops at the last track.
- **Previous** restarts the track if more than 3 seconds have played (or at the start of the queue without repeat-all); otherwise it goes back one track.
- **Autoplay:** at the end of a track, the next one plays if the account's autoplay setting is on. With autoplay off, the web player always stops, even when repeat is on; the apps still continue when repeat-all is on.
- **Play next** inserts a track right after the current one.
- **History:** one play log per playback, after 10 seconds or at the end of the track (see [Playback](playback.md#play-history)). Repeat-one counts as a new play.
- **Per device:** shuffle and repeat. **Per account:** quality and autoplay.
- **Unstable networks:** the apps retry before showing an error. iOS retries each audio chunk up to three times (after 0.5, 1 and 2 seconds) on connection loss, after 15 seconds without data, or on a `5xx`, but not on a `4xx`. Android reopens the stream with a fresh token and resumes from the same position, up to twice per track. The web player fetches a new playback ticket and resumes.
- **After a failure,** pressing Play resumes the same track from where it stopped, as the same play.
- **FLAC not supported:** play 320 kbps and say so once.

## Accessibility

- Every icon button has a label; focus rings are neon cyan with enough contrast; touch targets are at least 44×44.
- A "Skip to content" link, keyboard-operable seek bar with a spoken value ("1:23 of 3:45"), and shortcuts: `/` to search, `Space` to play or pause, `Esc` to close menus and sheets.
- Nothing is shown by colour alone: the playing track has an equalizer, active modes have a dot, errors have an icon and text.
- Destructive actions (deleting a playlist, revoking a device) need a second tap within 4 seconds.
- `prefers-reduced-motion` removes slides and bounces and keeps only fades.
- No sound plays without a user action. The Media Session API (play, pause, previous, next, seek) is used where available.

## Languages

Vietnamese is the default and English the alternative, switchable at any time without stopping the music.

| Platform | Strings | Switch |
| --- | --- | --- |
| Web | `src/control-plane/lang/{vi,en}`, same keys in both (checked by a test); `lang/*/listen_client.php` for the player script | `VI \| EN` in the top bar, sign-in pages and Settings; stored in the `filament_language_switcher_locale` cookie |
| API | Server messages follow `Accept-Language`; unsupported languages get Vietnamese | — |
| iOS | `IndieHouse/Resources/{vi,en}.lproj/Localizable.strings`, read through `L()` | Connection and sign-in screens, Settings |
| Android | `res/values` (vi) and `res/values-en` | Connection and sign-in screens, Settings |
