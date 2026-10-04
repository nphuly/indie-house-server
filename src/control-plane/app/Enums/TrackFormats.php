<?php

namespace App\Enums;

enum TrackFormats: string
{
    case HLS = 'hls';
    case MP3 = 'mp3';
    case FLAC = 'flac';
    case WAV = 'wav';
}
