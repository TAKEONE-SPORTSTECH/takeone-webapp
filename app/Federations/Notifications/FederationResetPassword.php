<?php

namespace App\Federations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password link a federation's staff receive — from the federation's own
 * forgot-password page, or when they are invited as staff. Same token broker
 * and email design as the platform's (members::emails.reset-password); only the
 * link differs: it opens /federation/password/reset, so they never leave the
 * federation's pages.
 */
class FederationResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('federations.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject(__('federations::messages.pw_mail_subject'))
            ->view('members::emails.reset-password', ['url' => $url]);
    }
}
