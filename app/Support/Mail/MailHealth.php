<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Can the platform send email RIGHT NOW? A login to the mail server — nothing
 * is sent — and what the last answer was.
 *
 * Why this exists: on the live server the Gmail password went stale and every
 * email failed — verifications, invoices, reminders — for months, with nobody
 * told (28 Oct 2026). `mail:health` asks every 15 minutes; the moment the
 * answer changes, the super-admins get an in-app notice (never an email: the
 * thing that is broken is email), and when it comes back the emails that
 * failed meanwhile are sent again (`mail:retry-failed`).
 */
final class MailHealth
{
    private const KEY = 'mail.health';

    /** Tests replace the login with their own answer: a closure returning null (fine) or an error. */
    public static ?\Closure $probe = null;

    /** null when sending works, else why not (never a secret: the server's own words, cut short). */
    public static function check(): ?string
    {
        if (self::$probe) {
            return (self::$probe)();
        }
        if (config('mail.default') !== 'smtp') {
            return null; // log / array / a provider's API: nothing to log in to
        }

        $c = config('mail.mailers.smtp');
        try {
            $t = new EsmtpTransport((string) $c['host'], (int) $c['port'], (int) $c['port'] === 465);
            $t->setUsername((string) $c['username']);
            $t->setPassword((string) $c['password']);
            $t->start();
            $t->stop();

            return null;
        } catch (Throwable $e) {
            return mb_substr($e->getMessage(), 0, 300);
        }
    }

    /** The last answer: ['ok' => bool, 'since' => ISO time it became so, 'error' => ?string]. */
    public static function state(): array
    {
        return Cache::get(self::KEY, ['ok' => true, 'since' => null, 'error' => null]);
    }

    /**
     * Keep a new answer, and say whether it CHANGED: 'down' (worked, now does not), 'up' (did not,
     * now does) or 'same'.
     */
    public static function record(?string $error): string
    {
        $was = self::state();
        $ok = $error === null;
        $change = $was['ok'] === $ok ? 'same' : ($ok ? 'up' : 'down');
        Cache::forever(self::KEY, [
            'ok' => $ok,
            'since' => $change === 'same' && $was['since'] ? $was['since'] : now()->toIso8601String(),
            'error' => $error,
        ]);

        return $change;
    }
}
