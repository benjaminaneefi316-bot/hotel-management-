<?php

declare(strict_types=1);

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Automated backup + retention.
 *
 * The dump → gzip → AES-256-GCM → store (local or S3) → verify pipeline and the
 * failure alert live in BackupService; these two commands are the scheduled
 * entry points. `run()` records the outcome and raises an alert instead of
 * throwing, so a failure never wedges the scheduler.
 */
Artisan::command('backup:run', function () {
    $this->info('Starting '.config('backup.driver', 'local').' backup...');

    $log = app(BackupService::class)->run();

    $this->info(sprintf(
        'Backup %s finished with status "%s" (%s).',
        $log->reference,
        $log->status,
        $log->humanSize(),
    ));

    return $log->status === \App\Models\BackupLog::STATUS_SUCCESS ? self::SUCCESS : self::FAILURE;
})->purpose('Dump, encrypt and store a database backup');

Artisan::command('backup:prune {--days=}', function () {
    $days = $this->option('days');
    $removed = app(BackupService::class)->prune($days !== null ? (int) $days : null);

    $this->info("Pruned {$removed} expired backup archive(s).");

    return self::SUCCESS;
})->purpose('Delete backup archives past the retention window');

Artisan::command('otp:prune', function () {
    $removed = app(\App\Services\OtpService::class)->purgeExpired();

    $this->info("Pruned {$removed} expired one-time code(s).");

    return self::SUCCESS;
})->purpose('Delete expired two-factor one-time codes');

// Nightly at 02:30 (server time), plus a weekly retention sweep. The schedule
// only runs where the Laravel scheduler is wired up (cron / supervisor / a
// platform scheduler); see docs/BACKUP_RESTORE.md and docs/AWS_DEPLOYMENT.md.
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:prune')
    ->weeklyOn(1, '03:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('otp:prune')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
