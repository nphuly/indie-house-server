<?php

namespace App\Filament\Widgets;

use chillerlan\QRCode\QRCode;
use Filament\Widgets\Widget;

class InstanceConnect extends Widget
{
    protected string $view = 'filament.widgets.instance-connect';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return parse_url((string) config('instance.base_url'), PHP_URL_SCHEME) === 'https';
    }

    protected function getViewData(): array
    {
        $manifestUrl = rtrim((string) config('instance.base_url'), '/').'/.well-known/indie-house';

        return [
            'manifestUrl' => $manifestUrl,
            'qrDataUri' => (new QRCode)->render($manifestUrl),
        ];
    }
}
