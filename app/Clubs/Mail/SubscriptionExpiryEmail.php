<?php

namespace App\Clubs\Mail;

use App\Clubs\Models\ClubMemberSubscription;
use App\Members\Models\User;
use App\Support\Mail\RetriesDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryEmail extends Mailable implements ShouldQueue
{
    use RetriesDelivery; // waits and tries again — App\Support\Mail\RetriesDelivery
    use Queueable, SerializesModels;

    public function __construct(
        public ClubMemberSubscription $subscription,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        $clubName = $this->subscription->tenant->club_name ?? config('app.name');
        $isPaid = $this->subscription->payment_status === 'paid';

        $subject = $isPaid
            ? "[{$clubName}] Your package expires in 3 days — Renew now"
            : "[{$clubName}] Your package expires in 3 days — Renew & complete payment";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $isPaid = $this->subscription->payment_status === 'paid';

        return new Content(
            view: $isPaid
                ? 'clubs::emails.subscription-expiry-paid'
                : 'clubs::emails.subscription-expiry-unpaid',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
