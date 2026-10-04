<?php

namespace App\Domain\Backup\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BackupType: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function getLabel(): string
    {
        return __("backup.enums.type.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Scheduled => 'info',
        };
    }
}
