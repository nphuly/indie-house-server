<?php

namespace App\Filament\Resources\Albums;

use App\Filament\Resources\Albums\Pages\CreateAlbums;
use App\Filament\Resources\Albums\Pages\EditAlbums;
use App\Filament\Resources\Albums\Pages\ListAlbums;
use App\Filament\Resources\Albums\Pages\ViewAlbums;
use App\Filament\Resources\Albums\Schemas\AlbumsForm;
use App\Filament\Resources\Albums\Schemas\AlbumsInfolist;
use App\Filament\Resources\Albums\Tables\AlbumsTable;
use App\Models\Media\Album;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AlbumsResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Album::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AlbumsForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AlbumsInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlbumsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlbums::route('/'),
            'create' => CreateAlbums::route('/create'),
            'view' => ViewAlbums::route('/{record}'),
            'edit' => EditAlbums::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('album.model_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('general.media-resource-group');
    }
}
