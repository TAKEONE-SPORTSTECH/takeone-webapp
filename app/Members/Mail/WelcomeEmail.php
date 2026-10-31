<?php

namespace App\Members\Mail;

use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Support\Mail\RetriesDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WelcomeEmail extends Mailable implements ShouldQueue
{
    use RetriesDelivery; // waits and tries again — App\Support\Mail\RetriesDelivery
    use Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Members\Models\User
     */
    public $user;

    /**
     * The guardian user instance.
     *
     * @var \App\Members\Models\User|null
     */
    public $guardian;

    /**
     * The relationship instance.
     *
     * @var \App\Members\Models\UserRelationship|null
     */
    public $relationship;

    /**
     * The intended redirect URL after verification.
     *
     * @var string|null
     */
    public $intended;

    /**
     * The signed verification link, built when the mail is CONSTRUCTED.
     *
     * Built here — on the box that dispatches the mail — rather than in
     * content() at send time: staging and production share one jobs queue, so
     * whichever box's worker sent it used to stamp its own APP_URL and signing
     * key on the link, and a link signed by the wrong box answers "invalid
     * signature". A public string serialises with the queued job, so the
     * sending worker can no longer change it.
     *
     * Valid for `auth.verification.expire` minutes (default 1440 = 24 h): the
     * mail may be sent hours late (RetriesDelivery), so a 60-minute link could
     * be dead on arrival.
     *
     * @var string
     */
    public string $verificationUrl = '';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, ?User $guardian = null, ?UserRelationship $relationship = null, ?string $intended = null)
    {
        $this->user = $user;
        $this->guardian = $guardian;
        $this->relationship = $relationship;
        $this->intended = $intended;
        $this->verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            array_filter([
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'intended' => $intended,
            ])
        );
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name', 'Club SaaS').' - '.$this->user->full_name,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'members::emails.welcome',
            with: [
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
