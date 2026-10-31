<?php

namespace App\Members\Mail;

use App\Members\Models\User;
use App\Support\Mail\RetriesDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member that somebody else set their password — a club admin at the
 * desk, or a platform administrator. Never carries the password itself; it exists
 * so a change can never happen without the member knowing.
 * Sent by App\Members\Support\AdminPasswordReset — see docs/platform/account-recovery.md.
 */
class PasswordChangedByAdminEmail extends Mailable
{
    use Queueable, SerializesModels;
    use RetriesDelivery; // waits and tries again — App\Support\Mail\RetriesDelivery

    public function __construct(public User $user, public ?string $clubName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password was changed - '.config('app.name', 'TAKEONE'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'members::emails.password-changed-by-admin',
            with: [
                'changedBy' => $this->clubName ?? config('app.name', 'TAKEONE'),
                'forgotUrl' => route('password.request'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
