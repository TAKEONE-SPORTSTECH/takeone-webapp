<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a super-admin regenerates (auto-generates) a new password for a
 * member. Delivers the new plaintext password so the member can sign in and
 * change it. Not queued — the admin wants the mail to leave immediately.
 */
class GeneratedPasswordEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $newPassword;

    public function __construct(User $user, string $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password has been reset - ' . config('app.name', 'TAKEONE'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generated-password',
            with: [
                'loginUrl' => route('login'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
