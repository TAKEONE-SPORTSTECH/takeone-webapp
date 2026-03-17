<?php

namespace App\Mail;

use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubNotificationEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClubNotification $notification,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        $clubName = $this->notification->tenant->club_name ?? config('app.name');

        return new Envelope(
            subject: '[' . $clubName . '] ' . $this->notification->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.club-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
