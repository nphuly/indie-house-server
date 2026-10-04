<?php

namespace App\Support;

use Illuminate\Support\Number;

class Duration
{
    /** Human total for a collection, e.g. "42 phút" or "1 hr 5 min". */
    public static function long(int $seconds): string
    {
        if ($seconds < 60) {
            return __('listen.duration.seconds', ['count' => max(0, $seconds)]);
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return __('listen.duration.minutes', ['count' => $minutes]);
        }

        return $minutes === 0
            ? __('listen.duration.hours', ['count' => $hours])
            : __('listen.duration.hours_minutes', ['hours' => $hours, 'minutes' => $minutes]);
    }

    /** A play count in the current language, e.g. "1.204 lượt nghe" or "1,204 plays". */
    public static function plays(int $count): string
    {
        return trans_choice('listen.play_count', $count, [
            'count' => Number::format($count, locale: app()->getLocale()),
        ]);
    }
}
