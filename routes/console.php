<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Email can never again fail silently for months (28 Oct 2026): check it can be sent, tell the
// super-admins in the app when that changes, and put failed emails back once it works (App\Support\Mail).
Schedule::command('mail:health')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('mail:retry-failed')->hourly()->withoutOverlapping();
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
 * The weigh-in deadline, enforced: entries that are not paid for and/or have
 * never been on the scale come off the list once `weigh_in_at` has passed.
 *
 * Only for an event whose organiser switched it on, and never for one that has
 * already started. Idempotent — what it removes is gone, so an hourly run does
 * nothing the second time, and anybody who pays up or weighs in between two
 * runs simply stays.
 */
Schedule::command('events:enforce-entry-deadline')->hourly()->withoutOverlapping();

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
// Staging and production share one database AND one uploads folder (UPLOADS_ROOT).
// An unmounted folder means pictures written to one box only — shout, don't drift.
// Passes quietly while UPLOADS_ROOT is unset.
Schedule::command('takeone:uploads-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure(fn () => \Illuminate\Support\Facades\Log::critical('Shared uploads folder is NOT mounted or not writable — uploads are landing on this box only.'));
// Margin / TakeNote: the Bin keeps a note 30 days, then it goes for good with every
// picture, recording and drawing in it; uploads taken out of every note go a day
// later. After the nightly backup, so the last copy of anything is always in one.
Schedule::command('devboard:sweep --apply')->dailyAt('04:30')->withoutOverlapping();
// TakeNote cards' reminders ("1 hour before it is due"): to the card's people and its watchers, once.
Schedule::command('devboard:remind')->everyMinute()->withoutOverlapping();
Schedule::command('goals:daily-encouragement')->dailyAt('09:00');
// Ranks & grading reminders — each sent once; a no-op while RANKS_ENABLED is off.
Schedule::command('ranks:reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('alerts:recheck-low-stock')->dailyAt('08:00');

// Court displays that enrolled and were never claimed. Enrolment is open by
// necessity (a fresh screen has no credential to offer) and grants access to
// nothing, so this is the other half of that trade — abandoned rows are swept
// up instead of accumulating. Claimed screens are never touched.
Schedule::command('court:pair --prune')->dailyAt('04:00');
// The Taekwondo fleet's own sweep. It shared the line above until its command
// was found to be shadowed by Karate's identical signature — so for as long as
// this schedule has existed it has been pruning one table twice and the other
// never. Same job, same window, the other fleet.
Schedule::command('taekwondo:court-pair --prune')->dailyAt('04:05');

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
| Translation doctor — finds failed, stuck or interrupted language runs and
| dead translators, fixes what it can, tells super-admins about the rest.
| A no-op unless TRANSLATION_AUTOFIX is on. As www-data: the cache is
| file-based and the cron runs as root, and root-owned cache files lock the
| web user out (the site 500s).
*/
Schedule::command('translate:doctor')->everyFiveMinutes()->withoutOverlapping()
    ->runInBackground()->user('www-data');

/*
| Duplicate watch (app/Duplicates) — finds people, clubs and entries that
| exist twice, fixes the certain ones when DUPLICATE_AUTOFIX is on, tells
| super-admins about the rest. A no-op unless DUPLICATE_WATCH is on.
| As www-data, for the same reason as the doctor above.
*/
Schedule::command('duplicates:watch')->hourlyAt(17)->withoutOverlapping()
    ->runInBackground()->user('www-data');
