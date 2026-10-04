<?php

namespace App\Filament\Resources\Artists\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ArtistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->label(__('artist.avatar'))
                    ->helperText(__('artist.avatar_hint'))
                    ->avatar()
                    // Filament's avatar preset shrinks uploads to 500 px, which
                    // looks soft in the player and on high-density screens.
                    ->automaticallyResizeImagesToWidth('1200')
                    ->automaticallyResizeImagesToHeight('1200')
                    ->disk('s3')
                    // Skip the storage lookup: a failed one drops the image from
                    // the form, and saving would then clear it.
                    ->fetchFileInformation(false)
                    ->directory(fn (?Model $record) => $record ? "artists/$record->id" : 'artists/_pending')
                    ->preventFilePathTampering(),
                FileUpload::make('cover_image')
                    ->label(__('artist.cover_image'))
                    ->previewable()
                    ->disk('s3')
                    ->fetchFileInformation(false)
                    ->directory(fn (?Model $record) => $record ? "artists/$record->id" : 'artists/_pending')
                    ->preventFilePathTampering(),
                TextInput::make('name')
                    ->label(__('artist.name'))
                    ->required()
                    ->live(true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label(__('artist.slug'))
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('bio')
                    ->label(__('artist.bio'))
                    ->rows(10)
                    ->columnSpanFull(),
            ]);
    }
}
