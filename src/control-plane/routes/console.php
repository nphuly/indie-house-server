<?php

use App\Support\Settings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('media:purge-pending-uploads --hours=24')
    ->daily()
    ->withoutOverlapping();

// Settings are stored in the database, which may not exist yet while running
// first-install commands such as key:generate and migrate.
try {
    if (Settings::get('backup', 'schedule_enabled', false)) {
        Schedule::command('backup:run')
            ->cron(Settings::get('backup', 'schedule_cron', '0 2 * * *'))
            ->name('run-backup')
            ->withoutOverlapping();
    }

    if (Settings::get('backup', 'clean_enabled', false)) {
        Schedule::command('backup:clean')
            ->cron(Settings::get('backup', 'clean_cron', '0 3 * * *'))
            ->name('clean-old-backups')
            ->withoutOverlapping();
    }
} catch (Throwable) {
    // Skip dynamic schedules until the database and settings table are ready.
}
