<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Inline SVG icons for the listener UI. One 24px grid and one stroke weight
 * (set in CSS) keep every glyph visually consistent; filled glyphs are
 * listed in FILLED and styled with `.icon--fill`.
 */
class Icon
{
    private const FILLED = ['play', 'pause', 'previous', 'next', 'more'];

    private const PATHS = [
        'home' => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'compass' => '<circle cx="12" cy="12" r="8.5"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'library' => '<path d="M5 4v16M9.5 4v16M13.5 5.2l4.8 14.6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'back' => '<path d="m14.5 5-7 7 7 7"/>',
        'forward' => '<path d="m9.5 5 7 7-7 7"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'play' => '<path d="M8 5.6v12.8a.9.9 0 0 0 1.37.77l10.2-6.4a.9.9 0 0 0 0-1.54L9.37 4.83A.9.9 0 0 0 8 5.6Z"/>',
        'pause' => '<rect x="6.5" y="5" width="4" height="14" rx="1.2"/><rect x="13.5" y="5" width="4" height="14" rx="1.2"/>',
        'previous' => '<rect x="5" y="5" width="2.4" height="14" rx="1.2"/><path d="M19 6.3v11.4a.8.8 0 0 1-1.25.66L9.6 12.66a.8.8 0 0 1 0-1.32l8.15-5.7A.8.8 0 0 1 19 6.3Z"/>',
        'next' => '<rect x="16.6" y="5" width="2.4" height="14" rx="1.2"/><path d="M5 6.3v11.4a.8.8 0 0 0 1.25.66l8.15-5.7a.8.8 0 0 0 0-1.32L6.25 5.64A.8.8 0 0 0 5 6.3Z"/>',
        'shuffle' => '<path d="M16.5 4H20v3.5M4 17.5h3c4.5 0 5.5-11 10-11h3M16.5 20H20v-3.5M4 6.5h3c1.8 0 2.9 1.4 3.7 3.2M13.3 14.3c.8 1.8 1.9 3.2 3.7 3.2h3"/>',
        'repeat' => '<path d="m17 3 3 3-3 3M20 6H8a4 4 0 0 0-4 4v1M7 21l-3-3 3-3M4 18h12a4 4 0 0 0 4-4v-1"/>',
        'queue' => '<path d="M4 6h16M4 11h10M4 16h7"/><path d="m15 13.5 5 3.25L15 20z"/>',
        'volume' => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.5 9a4 4 0 0 1 0 6M18.2 6.3a7.8 7.8 0 0 1 0 11.4"/>',
        'volume-low' => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.5 9a4 4 0 0 1 0 6"/>',
        'volume-mute' => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="m16 9.5 5 5M21 9.5l-5 5"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'more' => '<circle cx="5.5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="18.5" cy="12" r="1.6"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'history' => '<path d="M4.5 12A7.5 7.5 0 1 0 7 6.4L4.5 8.8"/><path d="M4.5 4.5v4.3h4.3"/><path d="M12 8.2V12l2.8 1.8"/>',
        'note' => '<path d="M9 17.5V6.2l10-2.2v11.5"/><circle cx="6.5" cy="17.5" r="2.5"/><circle cx="16.5" cy="15.5" r="2.5"/>',
        'sliders' => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
        'logout' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="m10 16-4-4 4-4M6 12h10"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="m3 3 18 18"/><path d="M10.6 5.6c.46-.07.92-.1 1.4-.1 6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.9 3.7M6.6 6.6C4 8.3 2.5 12 2.5 12S6 18.5 12 18.5c1.8 0 3.3-.5 4.6-1.2"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'pencil' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
        'disc' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.5"/>',
        'mic' => '<rect x="9" y="3.5" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v2.5"/>',
        'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.5 5.3 3.5 8.5s-1 6.2-3.5 8.5c-2.5-2.3-3.5-5.3-3.5-8.5s1-6.2 3.5-8.5Z"/>',
        'share' => '<path d="M12 14.5V3.5M8 7.5l4-4 4 4"/><path d="M5 12.5v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/>',
        'download' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/>',
        'device' => '<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 17.5h2"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c1.2-3.6 4-5.5 7.5-5.5s6.3 1.9 7.5 5.5"/>',
        'signal' => '<circle cx="12" cy="12" r="1.8"/><path d="M8.3 8.3a5.2 5.2 0 0 0 0 7.4M15.7 8.3a5.2 5.2 0 0 1 0 7.4M5.5 5.5a9.2 9.2 0 0 0 0 13M18.5 5.5a9.2 9.2 0 0 1 0 13"/>',
        'shield' => '<path d="M12 3.5 19 6v5.5c0 4.4-3 7.8-7 9-4-1.2-7-4.6-7-9V6z"/>',
    ];

    public static function svg(string $name, string $class = ''): HtmlString
    {
        $paths = self::PATHS[$name] ?? throw new InvalidArgumentException("Unknown icon [{$name}].");
        $classes = trim('icon'.(in_array($name, self::FILLED, true) ? ' icon--fill' : '').' '.$class);

        return new HtmlString('<svg class="'.e($classes).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$paths.'</svg>');
    }

    /** Raw path markup for the player script, which swaps glyphs in place. */
    public static function paths(string $name): string
    {
        return self::PATHS[$name] ?? throw new InvalidArgumentException("Unknown icon [{$name}].");
    }
}
