<?php

namespace App\Jobs;

use App\Mail\ClubNotificationEmail;
use App\Models\ClubNotification;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendClubNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ClubNotification $notification,
        public Collection $recipients
    ) {}

    public function handle(): void
    {
        $count = 0;

        $this->recipients->chunk(50)->each(function ($chunk) use (&$count) {
            foreach ($chunk as $recipient) {
                // Create in-app notification
                UserNotification::insertOrIgnore([
                    'club_notification_id' => $this->notification->id,
                    'user_id'              => $recipient->id,
                    'tenant_id'            => $this->notification->tenant_id,
                    'is_read'              => false,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                // Queue email
                Mail::to($recipient->email)->queue(
                    new ClubNotificationEmail($this->notification, $recipient)
                );

                $count++;
            }
        });

        // Update actual dispatched count
        $this->notification->update(['recipient_count' => $count]);
    }
}
