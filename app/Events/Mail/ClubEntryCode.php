<?php

namespace App\Events\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The six-digit code a club types to register on a club-hosted event's
 * "Register your club" screens (App\Events\Support\ClubEntry\PlatformClubEntry).
 * Sent only ever to an address already on the club's record. Plain text.
 */
class ClubEntryCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $clubName, public string $eventName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your code for '.$this->eventName);
    }

    public function content(): Content
    {
        return new Content(text: 'events::mail.club-entry-code');
    }
}
