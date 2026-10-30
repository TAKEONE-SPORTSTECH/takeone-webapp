<?php

namespace App\Console\Commands;

use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Support\Mail\MailHealth;
use Illuminate\Console\Command;

/**
 * Every 15 minutes (routes/console.php): can the platform send email?
 *
 * Only a CHANGE is reported — the super-admins are told in the app (never by
 * email, which is what is broken) when sending stops and again when it comes
 * back, and on the way back the emails that failed meanwhile are sent again.
 * See App\Support\Mail\MailHealth for why this exists.
 */
class MailHealthCheck extends Command
{
    protected $signature = 'mail:health';

    protected $description = 'Check that email can be sent; tell the super-admins when that changes, and resend what failed once it works again';

    public function handle(): int
    {
        $error = MailHealth::check();
        $change = MailHealth::record($error);

        if ($change === 'down') {
            $this->tell('mail_down', 'Email has stopped sending', 'Verification, invoice and reminder emails are failing. The mail server said: '.$error.' — they will be sent automatically once it works again.', 'bi-envelope-exclamation');
            $this->error('Email is DOWN: '.$error);

            return self::FAILURE;
        }

        if ($change === 'up') {
            $this->tell('mail_up', 'Email is sending again', 'The emails that failed while it was down are being sent now.', 'bi-envelope-check');
            $this->call('mail:retry-failed');
        }

        $this->info($error === null ? 'Email can be sent.' : 'Email is still down: '.$error);

        return $error === null ? self::SUCCESS : self::FAILURE;
    }

    private function tell(string $type, string $title, string $body, string $icon): void
    {
        User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id')
            ->each(fn ($id) => UserNotification::notifyUser((int) $id, $type, $title, ['body' => $body, 'icon' => $icon]));
    }
}
