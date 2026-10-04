<?php

namespace App\Filament\Resources\MobileAuthEvents;

use App\Filament\Resources\MobileAuthEvents\Pages\ListMobileAuthEvents;
use App\Models\Logs\MobileAuthEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MobileAuthEventResource extends Resource
{
    protected static ?string $model = MobileAuthEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ListBullet;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->label(__('mobile_admin.events.event'))
                    ->formatStateUsing(fn (string $state): string => trans()->has("mobile_admin.events.filter.{$state}")
                        ? __("mobile_admin.events.filter.{$state}")
                        : $state)
                    ->badge()
                    ->searchable(),
                TextColumn::make('user.email')->label(__('mobile_admin.user'))->placeholder('-')->searchable(),
                TextColumn::make('session.device_name')->label(__('mobile_admin.device'))->placeholder('-'),
                TextColumn::make('ip_address')->label('IP')->placeholder('-'),
                TextColumn::make('actor.email')->label('Admin')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label(__('mobile_admin.events.time'))->dateTime('d/m/Y H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'login' => __('mobile_admin.events.filter.login'),
                    'refresh' => __('mobile_admin.events.filter.refresh'),
                    'session_revoked' => __('mobile_admin.events.filter.session_revoked'),
                    'refresh_replay_detected' => __('mobile_admin.events.filter.refresh_replay_detected'),
                    'jwt_key_activated' => __('mobile_admin.events.filter.jwt_key_activated'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMobileAuthEvents::route('/')];
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
        return __('mobile_admin.events.model');
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_admin.events.label');
    }
}
