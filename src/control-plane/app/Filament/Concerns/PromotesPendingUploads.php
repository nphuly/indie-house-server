<?php

namespace App\Filament\Concerns;

use App\Support\PendingUpload;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * For create pages whose uploads move out of `_pending` in afterCreate().
 */
trait PromotesPendingUploads
{
    /**
     * The admin panel runs without transactions; these pages need one so a
     * failed move can undo the record it belongs to.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    /**
     * Moves one upload to its final key. If storage refuses, nothing is saved:
     * the create rolls back and the admin is asked to try again.
     */
    protected function promotePendingUpload(string $from, string $to): void
    {
        try {
            PendingUpload::promote($from, $to);
        } catch (RuntimeException) {
            $this->haltWithUploadError();
        }
    }

    /**
     * Stops the create when an upload did not reach storage, instead of saving
     * a record that points at a missing file.
     */
    protected function haltWithUploadError(): never
    {
        Notification::make()
            ->danger()
            ->title(__('general.upload_not_saved.title'))
            ->body(__('general.upload_not_saved.body'))
            ->persistent()
            ->send();

        $this->halt(shouldRollbackDatabaseTransaction: true);
    }
}
