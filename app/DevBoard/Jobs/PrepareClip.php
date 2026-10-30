<?php

namespace App\DevBoard\Jobs;

use App\DevBoard\Models\Asset;
use App\DevBoard\Services\Clips;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Prepares a video placed in a note (Clips::prepare) on the media queue, so the
 * upload answers at once and the note shows "preparing" until it is ready.
 * Carries only the asset id; a removed asset is simply skipped.
 */
class PrepareClip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $assetId) {}

    public function handle(Clips $clips): void
    {
        $asset = Asset::find($this->assetId);
        if ($asset) {
            $clips->prepare($asset);
        }
    }

    public function failed(\Throwable $e): void
    {
        $asset = Asset::find($this->assetId);
        if ($asset && $asset->status === 'processing') {
            if ($asset->source_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($asset->source_path);
            }
            $asset->forceFill(['status' => 'failed', 'source_path' => null])->save();
        }
    }
}
