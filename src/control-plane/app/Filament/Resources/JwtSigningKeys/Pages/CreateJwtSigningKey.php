<?php

namespace App\Filament\Resources\JwtSigningKeys\Pages;

use App\Filament\Resources\JwtSigningKeys\JwtSigningKeyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJwtSigningKey extends CreateRecord
{
    protected static string $resource = JwtSigningKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
