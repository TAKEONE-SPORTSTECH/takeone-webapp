<?php

namespace App\DevBoard\Mail;

use App\Support\Mail\RetriesDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Ghassan invited you to TakeNote" — sent to the address a friend was invited
 * with (TakeNoteFriendsController). Says only what the friend needs: whose
 * invitation it is, that the notebook is private, which email to sign in with,
 * and the link. Sent in the friend's language when their account has one,
 * else the inviter's (->locale(), set by the sender).
 */
class TakeNoteInvite extends Mailable
{
    use RetriesDelivery; // waits and tries again — App\Support\Mail\RetriesDelivery
    use Queueable, SerializesModels;

    public function __construct(public string $inviter, public string $email, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('devboard::messages.mi_subject', ['name' => $this->inviter]));
    }

    public function content(): Content
    {
        return new Content(view: 'devboard::mail.invite', text: 'devboard::mail.invite-text');
    }
}
