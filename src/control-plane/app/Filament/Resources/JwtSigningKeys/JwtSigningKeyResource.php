<?php

namespace App\Filament\Resources\JwtSigningKeys;

use App\Filament\Resources\JwtSigningKeys\Pages\CreateJwtSigningKey;
use App\Filament\Resources\JwtSigningKeys\Pages\ListJwtSigningKeys;
use App\Models\Auth\JwtSigningKey;
use App\Services\Auth\JwtSigningKeyManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;
use UnitEnum;

class JwtSigningKeyResource extends Resource
{
    protected static ?string $model = JwtSigningKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kid')->label('Key ID (kid)')->required()->maxLength(120)->regex('/\A[a-zA-Z0-9._-]+\z/')->unique(),
            TextInput::make('public_key_fingerprint')
                ->label(__('mobile_admin.keys.fingerprint'))
                ->helperText('openssl pkey -pubin -in public.key -outform DER | sha256sum')
                ->required()
                ->length(64)
                ->regex('/\A[a-f0-9]{64}\z/'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('kid')->label('kid')->searchable()->sortable()->weight('medium'),
                TextColumn::make('status')->label(__('mobile_admin.keys.status'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        JwtSigningKey::ACTIVE => __('mobile_admin.keys.active'),
                        JwtSigningKey::STAGED => __('mobile_admin.keys.staged'),
                        default => $state,
                    })
                    ->badge()->color(fn (string $state): string => match ($state) {
                        JwtSigningKey::ACTIVE => 'success',
                        JwtSigningKey::STAGED => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('public_key_fingerprint')->label('Fingerprint')->limit(16)->copyable(),
                TextColumn::make('activated_at')->label(__('mobile_admin.keys.activated_at'))->dateTime('d/m/Y H:i')->placeholder('-'),
                TextColumn::make('creator.email')->label(__('mobile_admin.keys.created_by'))->placeholder('-')->toggleable(),
            ])
            ->recordActions([
                    Action::make('activate')
                        ->label(__('mobile_admin.keys.activate'))
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('mobile_admin.keys.activate_description'))
                        ->visible(fn (JwtSigningKey $record): bool => $record->status !== JwtSigningKey::ACTIVE)
                        ->action(function (JwtSigningKey $record): void {
                            try {
                                app(JwtSigningKeyManager::class)->activate($record, auth()->user());
                                Notification::make()->title(__('mobile_admin.keys.activate_success', ['kid' => $record->kid]))->success()->send();
                            } catch (RuntimeException $exception) {
                                report($exception);
                                Notification::make()->title(__('mobile_admin.keys.activate_failed'))->body(__('mobile_admin.keys.activate_failed_help'))->danger()->send();
                            }
                        }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJwtSigningKeys::route('/'),
            'create' => CreateJwtSigningKey::route('/create'),
        ];
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
        return __('mobile_admin.keys.model');
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_admin.keys.label');
    }
}
