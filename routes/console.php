<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:send-expiry-reminders')->dailyAt('00:00');
Schedule::command('subscriptions:send-expired-notices')->dailyAt('00:05');
Schedule::command('expenses:process-recurring')->dailyAt('00:00');
Schedule::command('messages:prune-attachments')->hourly();

Schedule::command('duels:expire-pending')->hourly();

// Event notification milestones (enrolment opening/closing, weigh-in, the
// event-morning reminder). Every milestone is claimed in a ledger before it is
// delivered, so running this often is safe — it can never double-notify.
Schedule::command('events:send-notifications')->hourly()->withoutOverlapping();

/*
 * Nightly backup: a verified database snapshot + an archive of the upload
 * folders, pruned on a retention window and copied off-server when BACKUP_DISK
 * is set. Runs before the small hours so a failure is visible in the morning
 * rather than discovered when a restore is needed.
 *
 * onFailure so a broken backup shouts — a cron that silently stops backing up
 * is indistinguishable from one that works, until the day it matters.
 */
Schedule::command('takeone:backup')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Nightly backup FAILED — restore capability is compromised.'));
Schedule::command('goals:daily-encouragement')->dailyAt('09:00');
Schedule::command('alerts:recheck-low-stock')->dailyAt('08:00');
