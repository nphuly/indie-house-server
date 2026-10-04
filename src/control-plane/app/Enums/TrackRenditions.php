<?php

namespace App\Enums;

enum TrackRenditions: string
{
    case PREVIEW_30 = 'preview_30';

    case MP3_128 = 'mp3_128';

    case MP3_320 = 'mp3_320';

    case LOSSLESS = 'lossless';

    case HLS = 'hls';

    case HLS_128 = 'hls_128';

    case HLS_320 = 'hls_320';
}
