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

// Court displays that enrolled and were never claimed. Enrolment is open by
// necessity (a fresh screen has no credential to offer) and grants access to
// nothing, so this is the other half of that trade — abandoned rows are swept
// up instead of accumulating. Claimed screens are never touched.
Schedule::command('court:pair --prune')->dailyAt('04:00');

/*
|--------------------------------------------------------------------------
| Media storage
|--------------------------------------------------------------------------
|
| Two jobs that keep "the video is on the NAS" true rather than aspirational.
|
| The migration is the important one, and it is about a specific, ordinary
| failure: during a competition the share goes unreachable for twenty minutes,
| clips fall back to local disk (losing a bout is not an option), and the share
| comes back. Without this they stay here forever. It moves them up, verifies
| each one, repoints the record, and only then deletes the local copy — so no
| link is ever broken, and it is a no-op when there is nothing to move or no
| storage attached.
|
| The verify pass is the audit: it says out loud if any record's bytes are not
| where the record claims, instead of that being discovered by somebody pressing
| play in front of a hall.
|
*/
Schedule::command('media:migrate --auto')->hourly()->withoutOverlapping();
Schedule::command('media:verify --quiet-when-clean --orphans')->dailyAt('04:30');

/*
| Live streams, reconciled against the media server.
|
| The lifecycle hooks handle the ordinary case — a phone that stops, a phone that
| loses signal. This covers the one they cannot: the media server restarting
| mid-broadcast, which would otherwise leave a mat showing as on air forever and,
| worse, leave the recording of a fought bout sitting on disk unclaimed.
*/
Schedule::command('live:reap')->everyFiveMinutes()->withoutOverlapping();
