<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['audio_quality', 'autoplay', 'record_listening_history'])]
class UserPreference extends Model
{
    protected function casts(): array
    {
        return [
            'autoplay' => 'boolean',
            'record_listening_history' => 'boolean',
        ];
    }
}
