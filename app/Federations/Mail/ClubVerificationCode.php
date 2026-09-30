<?php

namespace App\Federations\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The six-digit code a club types to open its invitation. Plain text on purpose. */
class ClubVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $clubName, public string $eventName, public ?string $link = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your code for '.$this->eventName);
    }

    public function content(): Content
    {
        return new Content(text: 'federations::mail.club-code');
    }
}
