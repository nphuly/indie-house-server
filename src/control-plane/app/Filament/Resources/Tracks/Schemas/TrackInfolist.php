<?php

namespace App\Filament\Resources\Tracks\Schemas;

use App\Models\Media\Track;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Illuminate\Support\Number;

class TrackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.tracks.audio-preview')
                    ->columnSpanFull(),
                Section::make(__('track.sections.basic'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('track.general.title'))
                            ->weight('medium'),
                        TextEntry::make('artist.name')
                            ->label(__('track.general.artist')),
                        TextEntry::make('status')
                            ->label(__('track.general.status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Track::statusLabel($state))
                            ->color(fn (?string $state): string => Track::statusColor($state)),
                        TextEntry::make('genre')
                            ->label(__('track.general.genre'))
                            ->formatStateUsing(fn (?string $state): string => $state ? __("genre.{$state}") : '-'),
                        TextEntry::make('formatted_duration')
                            ->label(__('track.general.duration')),
                        TextEntry::make('play_count')
                            ->label(__('track.general.play_count'))
                            ->numeric(),
                        TextEntry::make('slug')
                            ->label(__('track.general.slug'))
                            ->copyable(),
                        TextEntry::make('public_id')
                            ->label(__('track.general.public_id'))
                            ->copyable(),
                        TextEntry::make('raw_file_key')
                            ->label(__('track.general.raw_file_key'))
                            ->copyable()
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpan(2),
                        TextEntry::make('last_error_message')
                            ->label(__('track.general.last_error_message'))
                            ->visible(fn (?string $state): bool => filled($state))
                            ->formatStateUsing(fn (?string $state): string => __('track.general.processing_error_help'))
                            ->color('danger')
                            ->columnSpanFull(),
                        TextEntry::make('lyrics')
                            ->label(__('track.general.lyrics'))
                            ->placeholder('-')
                            ->formatStateUsing(fn (string $state) => nl2br($state))
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('track.sections.renditions'))
                    ->schema([
                        RepeatableEntry::make('renditions')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('track.renditions.quality_label')),
                                TableColumn::make(__('track.renditions.format')),
                                TableColumn::make(__('track.renditions.bitrate')),
                                TableColumn::make(__('track.renditions.size')),
                                TableColumn::make(__('track.renditions.file_key')),
                            ])
                            ->schema([
                                TextEntry::make('quality_label')
                                    ->badge(),
                                TextEntry::make('format')
                                    ->badge()
                                    ->color(fn (?string $state): string => $state === 'hls' ? 'info' : 'gray'),
                                TextEntry::make('bitrate_kbps')
                                    ->formatStateUsing(fn (?int $state): string => filled($state) ? "{$state} kbps" : '-'),
                                TextEntry::make('file_size_bytes')
                                    ->formatStateUsing(fn (?int $state): string => filled($state) ? Number::fileSize($state) : '-'),
                                TextEntry::make('file_key')
                                    ->copyable()
                                    ->limit(64),
                            ])
                            ->contained(false),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
