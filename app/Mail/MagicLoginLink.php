<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MagicLoginLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;

    public ?string $intended;

    public function __construct(User $user, ?string $intended = null)
    {
        $this->user = $user;
        $this->intended = $intended;
    }

    public function envelope()
    {
        return new Envelope(
            subject: 'Your login link for '.config('app.name', 'TAKEONE'),
        );
    }

    public function content()
    {
        return new Content(
            view: 'emails.magic-login',
            with: [
                'loginUrl' => URL::temporarySignedRoute('login.magic.verify', now()->addMinutes(30), array_filter([
                    'user' => $this->user->getKey(),
                    'intended' => $this->intended,
                ])),
                'user' => $this->user,
            ],
        );
    }

    public function attachments()
    {
        return [];
    }
}
