<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Genre: string implements HasLabel
{
    case POP = 'pop';
    case ROCK = 'rock';
    case INDIE = 'indie';
    case ALTERNATIVE = 'alternative';
    case ELECTRONIC = 'electronic';
    case HOUSE = 'house';
    case TECHNO = 'techno';
    case TRANCE = 'trance';
    case HIP_HOP = 'hip_hop';
    case RNB = 'rnb';
    case SOUL = 'soul';
    case JAZZ = 'jazz';
    case BLUES = 'blues';
    case COUNTRY = 'country';
    case FOLK = 'folk';
    case METAL = 'metal';
    case PUNK = 'punk';
    case REGGAE = 'reggae';
    case LATIN = 'latin';
    case CLASSICAL = 'classical';
    case WORLD = 'world';
    case EXPERIMENTAL = 'experimental';
    case SOUNDTRACK = 'soundtrack';
    case GOSPEL = 'gospel';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return __('genre.'.$this->value);
    }
}
