<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InstanceConnect;
use App\Filament\Widgets\MusicOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation');
    }

    public function getTitle(): string
    {
        return __('dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            MusicOverview::class,
            InstanceConnect::class,
            ...parent::getWidgets(),
        ];
    }
}
