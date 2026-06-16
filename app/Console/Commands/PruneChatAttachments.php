<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneChatAttachments extends Command
{
    protected $signature = 'messages:prune-attachments';

    protected $description = 'Delete expired chat attachment files and leave an "expired" tombstone.';

    public function handle(): int
    {
        $disk  = 'local';
        $count = 0;

        Message::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_expires_at', '<', now())
            ->chunkById(200, function ($messages) use ($disk, &$count) {
                foreach ($messages as $message) {
                    Storage::disk($disk)->delete($message->attachment_path);
                    // Null the path (keeps kind/name so the UI shows "expired").
                    $message->forceFill(['attachment_path' => null])->save();
                    $count++;
                }
            });

        $this->info("Pruned {$count} expired attachment(s).");

        return self::SUCCESS;
    }
}
