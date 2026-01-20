<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class WelcomeEmail extends Mailable
{

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The guardian user instance.
     *
     * @var \App\Models\User|null
     */
    public $guardian;

    /**
     * The relationship instance.
     *
     * @var \App\Models\UserRelationship|null
     */
    public $relationship;

    /**
     * Create a new message instance.
     *
     * @param \App\Models\User $user
     * @param \App\Models\User|null $guardian
     * @param \App\Models\UserRelationship|null $relationship
     * @return void
     */
    public function __construct(User $user, ?User $guardian = null, ?UserRelationship $relationship = null)
    {
        $this->user = $user;
        $this->guardian = $guardian;
        $this->relationship = $relationship;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name', 'Club SaaS') . ' - ' . $this->user->full_name,
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
            view: 'emails.welcome',
            with: [
                'verificationUrl' => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                    'id' => $this->user->getKey(),
                    'hash' => sha1($this->user->getEmailForVerification()),
                ]),
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
