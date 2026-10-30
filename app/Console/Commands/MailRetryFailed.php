<?php

namespace App\Console\Commands;

use App\Support\Mail\MailHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hourly (routes/console.php), and straight away when `mail:health` sees email
 * working again: put the EMAILS that failed back in the queue.
 *
 * Only emails (queued mailables and notifications) — no other kind of job —
 * that failed in the last few days, each at most a few times, and only while
 * sending works: retrying into a broken mail server just burns the attempts.
 * Every one then gets App\Support\Mail\RetriesDelivery's waits again.
 */
class MailRetryFailed extends Command
{
    private const MAIL_JOBS = ['Illuminate\\Mail\\SendQueuedMailable', 'Illuminate\\Notifications\\SendQueuedNotifications'];

    protected $signature = 'mail:retry-failed
        {--hours=72 : only emails that failed this recently}
        {--max=3 : how many times one email is put back}
        {--force : even while mail:health says sending is down}';

    protected $description = 'Put failed emails back in the queue (only emails, only recent ones, a few times each)';

    public function handle(): int
    {
        if (! $this->option('force') && ! MailHealth::state()['ok']) {
            $this->warn('Email is down (mail:health) — nothing put back; it happens when sending works again.');

            return self::SUCCESS;
        }

        $max = max(1, (int) $this->option('max'));
        $since = now()->subHours(max(1, (int) $this->option('hours')));
        $again = [];
        $gaveUp = 0;

        DB::table('failed_jobs')->where('failed_at', '>=', $since)->orderBy('id')->get(['uuid', 'payload'])
            ->each(function ($job) use ($max, &$again, &$gaveUp) {
                $payload = json_decode($job->payload, true) ?: [];
                if (! in_array($payload['data']['commandName'] ?? null, self::MAIL_JOBS, true)) {
                    return;
                }
                $key = 'mail.retry.'.$job->uuid;
                $times = (int) Cache::get($key, 0);
                if ($times >= $max) {
                    $gaveUp++;

                    return;
                }
                Cache::put($key, $times + 1, now()->addDays(7));
                $again[] = $job->uuid;
            });

        if ($again) {
            $this->call('queue:retry', ['id' => $again]);
        }
        $this->info(count($again).' email(s) put back in the queue'.($gaveUp ? ', '.$gaveUp.' given up on after '.$max.' tries' : '').'.');

        return self::SUCCESS;
    }
}
