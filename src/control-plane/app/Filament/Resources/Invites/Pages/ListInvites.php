<?php

namespace App\Filament\Resources\Invites\Pages;

use App\Filament\Resources\Invites\InviteResource;
use App\Services\Auth\InviteService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInvites extends ListRecords
{
    protected static string $resource = InviteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInvite')
                ->label(__('invite.create'))
                ->form([
                    TextInput::make('email')->label(__('invite.email'))->email()->maxLength(255)->nullable(),
                    TextInput::make('max_uses')->label(__('invite.max_uses'))->numeric()->integer()->minValue(1)->maxValue(100)->default(1)->required(),
                    TextInput::make('expires_in_days')->label(__('invite.expires_in_days'))->numeric()->integer()->minValue(1)->maxValue(90)->default(7)->required(),
                ])
                ->action(function (array $data): void {
                    $created = app(InviteService::class)->create(auth()->user(), $data['email'] ?? null, (int) $data['max_uses'], (int) $data['expires_in_days']);
                    Notification::make()->title(__('invite.created_title'))
                        ->body(__('invite.copy_once')."\n".route('invite.show', $created['code']))
                        ->persistent()->success()->send();
                }),
        ];
    }
}
