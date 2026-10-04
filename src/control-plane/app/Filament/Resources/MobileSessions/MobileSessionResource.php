<?php

namespace App\Filament\Resources\MobileSessions;

use App\Filament\Resources\MobileSessions\Pages\ListMobileSessions;
use App\Models\Auth\MobileSession;
use App\Services\Auth\MobileTokenService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class MobileSessionResource extends Resource
{
    protected static ?string $model = MobileSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DevicePhoneMobile;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_used_at', 'desc')
            ->columns([
                TextColumn::make('user.email')->label(__('mobile_admin.user'))->searchable()->sortable(),
                TextColumn::make('device_name')->label(__('mobile_admin.device'))->placeholder(__('mobile_admin.unknown'))->searchable(),
                TextColumn::make('platform')->label(__('mobile_admin.platform'))->placeholder('-')->badge(),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
                TextColumn::make('last_used_at')->label(__('mobile_admin.sessions.last_used'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('expires_at')->label(__('mobile_admin.sessions.expires'))->dateTime('d/m/Y H:i')->sortable(),
                IconColumn::make('revoked_at')->label(__('mobile_admin.sessions.revoked'))->boolean(fn (MobileSession $record): bool => $record->revoked_at !== null),
                TextColumn::make('revoked_reason')->label(__('mobile_admin.sessions.revoked_reason'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('replay_detected_at')->label(__('mobile_admin.sessions.replay_detected'))->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('revoked_at')->label(__('mobile_admin.sessions.revoked')),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('mobile_admin.sessions.revoke'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MobileSession $record): bool => $record->revoked_at === null)
                    ->action(function (MobileSession $record): void {
                        app(MobileTokenService::class)->revokeSession($record, 'admin_single_revoke', auth()->user());
                        Notification::make()->title(__('mobile_admin.sessions.revoke_success'))->success()->send();
                    }),
                Action::make('revoke_all_for_user')
                    ->label(__('mobile_admin.sessions.revoke_all'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('mobile_admin.sessions.revoke_all_description'))
                    ->action(function (MobileSession $record): void {
                        $count = app(MobileTokenService::class)->revokeAllForUser($record->user, 'admin_revoke_all', auth()->user());
                        Notification::make()->title(__('mobile_admin.sessions.revoke_all_success', ['count' => $count]))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMobileSessions::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
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
        return __('mobile_admin.sessions.model');
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_admin.sessions.label');
    }
}
