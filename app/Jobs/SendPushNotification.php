<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a native push (FCM) to one user's devices off the request cycle.
 * Runs alongside the existing MQTT push — MQTT reaches open apps live; this
 * reaches the OS tray even when the app is closed.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  array<string,mixed>  $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmService $fcm): void
    {
        if (! $fcm->enabled()) {
            return;
        }

        $fcm->sendToUser($this->userId, $this->title, $this->body, $this->data);
    }
}
