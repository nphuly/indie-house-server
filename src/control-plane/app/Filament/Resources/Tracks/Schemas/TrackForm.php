<?php

namespace App\Filament\Resources\Tracks\Schemas;

use App\Enums\Genre;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('track.sections.basic'))
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('artist_id')
                            ->label(__('track.general.artist'))
                            ->relationship('artist', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label(__('track.general.title'))
                            ->required()
                            ->live(true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label(__('track.general.slug'))
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('genre')
                            ->label(__('track.general.genre'))
                            ->options(Genre::class)
                            ->searchable()
                            ->required(),
                        Toggle::make('is_published')
                            ->label(__('track.general.is_published'))
                            ->onColor('success')
                            ->default(false),
                        Textarea::make('lyrics')
                            ->label(__('track.general.lyrics'))
                            ->rows(10)
                            ->columnSpanFull()
                    ]),
                Section::make(__('track.sections.file_processing'))
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('raw_file_key')
                            ->label(__('track.general.raw_file_key'))
                            ->disk('s3')
                            ->directory(function (?Model $record) {
                                if ($record) {
                                    return $record->raw_file_key;
                                }
                                else {
                                    return 'tracks/_pending';
                                }
                            })
                            ->acceptedFileTypes([
                                'audio/flac',
                                'audio/x-flac',
                                'audio/wav',
                                'audio/x-wav',
                                'audio/mpeg',
                                'audio/mp3'
                            ])
                            ->maxSize(500 * 1024)
                            ->required()
                            ->preventFilePathTampering()
                            ->uploadProgressIndicatorPosition('left')
                    ])
            ]);
    }
}
