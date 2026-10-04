<?php

namespace App\Filament\Resources\StreamAccesses;

use App\Filament\Resources\StreamAccesses\Pages\CreateStreamAccess;
use App\Filament\Resources\StreamAccesses\Pages\EditStreamAccess;
use App\Filament\Resources\StreamAccesses\Pages\ListStreamAccesses;
use App\Models\Auth\UserStreamAccess;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StreamAccessResource extends Resource
{
    protected static ?string $model = UserStreamAccess::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label(__('mobile_admin.user'))->relationship('user', 'email')->searchable()->preload()->required()->unique(ignoreRecord: true),
            Toggle::make('high_quality_enabled')->label(__('mobile_admin.access.high_quality'))->default(true),
            Toggle::make('lossless_enabled')->label(__('mobile_admin.access.lossless'))->helperText(__('mobile_admin.access.lossless_help'))->default(false),
            DateTimePicker::make('streaming_blocked_until')->label(__('mobile_admin.access.blocked_until'))->seconds(false),
            TextInput::make('streaming_block_reason')->label(__('mobile_admin.access.block_reason'))->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.email')->label(__('mobile_admin.user'))->searchable()->sortable(),
            IconColumn::make('high_quality_enabled')->label(__('mobile_admin.access.high_quality'))->boolean(),
            IconColumn::make('lossless_enabled')->label(__('mobile_admin.access.lossless'))->boolean(),
            TextColumn::make('streaming_blocked_until')->label(__('mobile_admin.access.blocked_until'))->dateTime('d/m/Y H:i')->placeholder('-')->sortable(),
            TextColumn::make('streaming_block_reason')->label(__('mobile_admin.access.block_reason'))->placeholder('-')->wrap(),
            TextColumn::make('updated_at')->label(__('mobile_admin.access.updated'))->dateTime('d/m/Y H:i')->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStreamAccesses::route('/'),
            'create' => CreateStreamAccess::route('/create'),
            'edit' => EditStreamAccess::route('/{record}/edit'),
        ];
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
        return __('mobile_admin.access.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_admin.access.label');
    }
}
