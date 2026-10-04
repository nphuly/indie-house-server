<?php

namespace App\Filament\Resources\Albums\Schemas;

use App\Enums\Genre;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AlbumsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('album.sections.basic'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('artist_id')
                            ->label(__('album.artist'))
                            ->relationship('artist', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('tracks', [])),
                        TextInput::make('title')
                            ->label(__('album.title'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label(__('album.slug'))
                            ->required()
                            ->unique(ignoreRecord: true),
                        FileUpload::make('cover_image')
                            ->label(__('album.cover_image'))
                            ->helperText(__('album.cover_hint'))
                            ->image()
                            ->disk('s3')
                            // Skip the storage lookup: a failed one drops the image
                            // from the form, and saving would then clear it.
                            ->fetchFileInformation(false)
                            ->directory(fn (?Model $record): string => $record ? "albums/{$record->id}" : 'albums/_pending')
                            ->preventFilePathTampering()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('album.description'))
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('album.sections.upload_tracks'))
                    ->visible(fn (?Model $record): bool => $record === null)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('track_drafts')
                            ->label(__('album.tracks'))
                            ->collapsible()
                            ->reorderableWithDragAndDrop()
                            ->addActionLabel(__('album.add_track'))
                            ->minItems(1)
                            ->itemLabel(fn (array $state): string => filled($state['title'] ?? null)
                                ? $state['title']
                                : __('album.new_track'))
                            ->schema([
                                FileUpload::make('raw_file_key')
                                    ->label(__('album.track_file'))
                                    ->disk('s3')
                                    ->directory('tracks/_pending')
                                    ->acceptedFileTypes([
                                        'audio/flac',
                                        'audio/x-flac',
                                        'audio/wav',
                                        'audio/x-wav',
                                        'audio/mpeg',
                                        'audio/mp3',
                                    ])
                                    ->maxSize(500 * 1024)
                                    ->required()
                                    ->preventFilePathTampering()
                                    ->columnSpanFull(),
                                TextInput::make('title')
                                    ->label(__('track.general.title'))
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->label(__('track.general.slug'))
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('genre')
                                    ->label(__('track.general.genre'))
                                    ->options(Genre::class)
                                    ->searchable()
                                    ->required(),
                                Toggle::make('is_published')
                                    ->label(__('track.general.is_published'))
                                    ->default(false),
                                Textarea::make('lyrics')
                                    ->label(__('track.general.lyrics'))
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('album.sections.tracks'))
                    ->visible(fn (?Model $record): bool => $record !== null)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tracks')
                            ->label(__('album.tracks'))
                            ->relationship(
                                name: 'tracks',
                                titleAttribute: 'title',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('artist_id', $get('artist_id')),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->reorderable()
                            ->helperText(__('album.tracks_edit_help')),
                    ]),
            ]);
    }
}
