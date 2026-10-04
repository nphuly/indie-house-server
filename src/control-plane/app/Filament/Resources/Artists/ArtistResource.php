<?php

namespace App\Filament\Resources\Artists;

use App\Filament\Resources\Artists\Pages\CreateArtist;
use App\Filament\Resources\Artists\Pages\EditArtist;
use App\Filament\Resources\Artists\Pages\ListArtists;
use App\Filament\Resources\Artists\Pages\ViewArtist;
use App\Filament\Resources\Artists\Schemas\ArtistForm;
use App\Filament\Resources\Artists\Schemas\ArtistInfolist;
use App\Filament\Resources\Artists\Tables\ArtistsTable;
use App\Models\Media\Artist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ArtistResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Artist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Microphone;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ArtistForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArtistInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArtistsTable::configure($table);
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
            'index' => ListArtists::route('/'),
            'create' => CreateArtist::route('/create'),
            'view' => ViewArtist::route('/{record}'),
            'edit' => EditArtist::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('artist.model_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('general.media-resource-group');
    }
}
