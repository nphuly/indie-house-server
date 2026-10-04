<?php

namespace App\Filament\Pages;

use App\Domain\Backup\Enums\BackupStatus;
use App\Domain\Backup\Models\BackupRecord;
use App\Domain\Backup\Services\BackupService;
use App\Jobs\RunBackupJob;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BackupManager extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CircleStack;

    protected static ?int $navigationSort = 50;

    public function getTitle(): string|Htmlable
    {
        return __('backup.manager.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('backup.manager.nav_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('backup.navigation.group');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BackupRecord::query())
            ->columns([
                TextColumn::make('filename')
                    ->label(__('backup.manager.col_filename'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('-')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('size_bytes')
                    ->label(__('backup.manager.col_size'))
                    ->alignEnd()
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (mixed $state): string => (int) $state > 0 ? $this->formatBytes((int) $state) : '-'),
                TextColumn::make('type')->label(__('backup.manager.col_type'))->badge(),
                TextColumn::make('status')->label(__('backup.manager.col_status'))->badge(),
                TextColumn::make('creator.name')
                    ->label(__('backup.manager.col_created_by'))
                    ->placeholder(__('backup.manager.system')),
                TextColumn::make('created_at')
                    ->label(__('backup.manager.col_created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('backup.manager.action_download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (BackupRecord $record): string => route('backup.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (BackupRecord $record): bool => $record->status === BackupStatus::Completed),
                Action::make('restore')
                    ->label(__('backup.manager.action_restore'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('backup.manager.restore_modal_title'))
                    ->modalDescription(__('backup.manager.restore_modal_body'))
                    ->modalSubmitActionLabel(__('backup.manager.action_restore'))
                    ->visible(fn (BackupRecord $record): bool => $record->status === BackupStatus::Completed)
                    ->action(function (BackupRecord $record): void {
                        try {
                            app(BackupService::class)->restore($record);
                            Notification::make()
                                ->title(__('backup.manager.restore_success', ['filename' => $record->filename]))
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->title(__('backup.manager.restore_failed'))
                                ->body(__('backup.manager.restore_failed_help'))
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('delete')
                    ->label(__('backup.manager.action_delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('backup.manager.delete_modal_title'))
                    ->action(function (BackupRecord $record): void {
                        app(BackupService::class)->deleteRecord($record);
                        Notification::make()->title(__('backup.manager.delete_success'))->success()->send();
                    }),
            ])
            ->poll('5s')
            ->paginated([15, 30, 50])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('backup.manager.no_data'))
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_backup')
                ->label(__('backup.manager.run_now'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->action(function (): void {
                    $record = app(BackupService::class)->createManualRecord(auth()->id());
                    RunBackupJob::dispatch($record->id);
                    Notification::make()->title(__('backup.manager.run_now_success'))->info()->send();
                }),
            Action::make('scan_directory')
                ->label(__('backup.manager.scan_directory'))
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function (): void {
                    $count = app(BackupService::class)->scanDirectory();
                    Notification::make()
                        ->title($count > 0
                            ? __('backup.manager.scan_success', ['count' => $count])
                            : __('backup.manager.scan_empty'))
                        ->success()
                        ->send();
                }),
            Action::make('clean_backups')
                ->label(__('backup.manager.clean_now'))
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->action(function (): void {
                    $count = app(BackupService::class)->clean();
                    Notification::make()
                        ->title($count > 0
                            ? __('backup.manager.clean_now_success', ['count' => $count])
                            : __('backup.manager.clean_now_empty'))
                        ->success()
                        ->send();
                }),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => number_format($bytes / 1_073_741_824, 2).' GB',
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 2).' MB',
            $bytes >= 1_024 => number_format($bytes / 1_024, 2).' KB',
            default => $bytes.' B',
        };
    }
}
