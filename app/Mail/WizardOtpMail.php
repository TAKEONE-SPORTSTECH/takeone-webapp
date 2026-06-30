<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WizardOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $code;
    public User $user;

    public function __construct(string $code, User $user)
    {
        $this->code = $code;
        $this->user = $user;
    }

    public function envelope()
    {
        return new Envelope(
            subject: 'Your verification code: ' . $this->code,
        );
    }

    public function content()
    {
        return new Content(
            view: 'emails.wizard-otp',
            with: [
                'code' => $this->code,
                'name' => $this->user->full_name ?: $this->user->name,
            ],
        );
    }

    public function attachments()
    {
        return [];
    }
}
