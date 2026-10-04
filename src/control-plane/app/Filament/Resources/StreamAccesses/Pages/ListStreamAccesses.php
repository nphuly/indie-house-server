<?php

namespace App\Filament\Resources\StreamAccesses\Pages;

use App\Filament\Resources\StreamAccesses\StreamAccessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStreamAccesses extends ListRecords
{
    protected static string $resource = StreamAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
