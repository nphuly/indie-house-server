<?php

namespace App\Filament\Resources\JwtSigningKeys\Pages;

use App\Filament\Resources\JwtSigningKeys\JwtSigningKeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJwtSigningKeys extends ListRecords
{
    protected static string $resource = JwtSigningKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('mobile_admin.keys.register'))];
    }
}
