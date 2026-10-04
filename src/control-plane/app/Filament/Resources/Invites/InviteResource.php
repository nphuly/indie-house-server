<?php

namespace App\Filament\Resources\Invites;

use App\Filament\Resources\Invites\Pages\ListInvites;
use App\Models\Auth\Invite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InviteResource extends Resource
{
    protected static ?string $model = Invite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Ticket;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->label(__('invite.email'))->placeholder(__('invite.any_email'))->searchable(),
                TextColumn::make('uses_count')->label(__('invite.uses'))->formatStateUsing(fn (Invite $record): string => "{$record->uses_count} / {$record->max_uses}"),
                TextColumn::make('expires_at')->label(__('invite.expires'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('revoked_at')->label(__('invite.revoked'))->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('creator.email')->label(__('invite.creator'))->placeholder('—'),
                TextColumn::make('redemptions_count')->counts('redemptions')->label(__('invite.audit')),
            ])
            ->recordActions([
                Action::make('redemptions')
                    ->label(__('invite.audit'))
                    ->modalHeading(__('invite.audit'))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Invite $record) => view('filament.invites.redemptions', [
                        'redemptions' => $record->redemptions()->with('user')->latest('created_at')->get(),
                    ])),
                Action::make('revoke')
                    ->label(__('invite.revoke'))->color('danger')->requiresConfirmation()
                    ->visible(fn (Invite $record): bool => $record->revoked_at === null)
                    ->action(function (Invite $record): void {
                        $record->forceFill(['revoked_at' => now()])->save();
                        Notification::make()->title(__('invite.revoked_success'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvites::route('/')];
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('mobile_admin.group');
    }

    public static function getModelLabel(): string
    {
        return __('invite.model');
    }

    public static function getNavigationLabel(): string
    {
        return __('invite.label');
    }
}
