<?php

namespace App\DevBoard\Commands;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\DevBoard\Services\NoteHtml;
use App\Support\StoragePath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the promise the Bin makes ("kept for 30 days, then gone for good")
 * and lets nothing uploaded outlive its note:
 *
 *   1. notes (cards) in the Bin for more than 30 days are deleted for good —
 *      with every picture, voice note, video, drawing and their files;
 *   2. single notes binned on a card for more than 30 days, the same;
 *   3. anything uploaded to a note and then taken out of it (a photo deleted
 *      from the text, a recording removed, a drawing re-done) is deleted once
 *      it has been out of every note of its card for a day. What a drawing
 *      was drawn over (its original photo) counts as in use while the drawing
 *      is. A day of grace covers an upload whose note has not saved yet.
 *
 * Files are always deleted before their rows (DeletesUploadedFiles on Asset
 * and Item; Card cascades one record at a time). Dry run unless --apply.
 */
class Sweep extends Command
{
    protected $signature = 'devboard:sweep {--apply : delete; without it, only report} {--days=30 : how long the Bin keeps things}';

    protected $description = "Empty Margin's Bin after its keep period and delete uploads no note uses any more";

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $grace = now()->subDay();

        // 1. cards in the Bin past the keep period
        $cards = Card::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();
        foreach ($cards as $card) {
            $this->line(($apply ? 'deleting' : 'would delete')." card {$card->uuid} (binned {$card->deleted_at->format('j M Y')}, {$card->assets()->count()} file(s))");
            if ($apply) {
                $dir = StoragePath::platform('devboard/cards/'.$card->uuid);
                $card->forceDelete(); // items, assets and their files first (Card::booted)
                $this->dropEmptyDir($dir);
            }
        }

        // 2. single notes binned on a card that is still there
        $items = Item::onlyTrashed()->where('deleted_at', '<', $cutoff)->whereHas('card')->get();
        foreach ($items as $item) {
            $this->line(($apply ? 'deleting' : 'would delete')." note {$item->uuid} (binned {$item->deleted_at->format('j M Y')})");
            if ($apply) {
                $item->forceDelete();
            }
        }

        // 3. uploads no note of their card uses any more
        $orphans = 0;
        Card::withTrashed()->with('assets')->whereHas('assets')->each(function (Card $card) use ($apply, $grace, &$orphans) {
            $used = $this->inUse($card);
            foreach ($card->assets as $asset) {
                if (isset($used[$asset->uuid]) || $asset->created_at > $grace) {
                    continue;
                }
                $orphans++;
                $this->line(($apply ? 'deleting' : 'would delete')." unused {$asset->kind} {$asset->uuid} on card {$card->uuid}");
                if ($apply) {
                    $asset->delete(); // its files first (DeletesUploadedFiles)
                }
            }
        });

        $this->info(sprintf('%s: %d card(s) and %d note(s) past the Bin, %d unused upload(s).',
            $apply ? 'Deleted' : 'Dry run', $cards->count(), $items->count(), $orphans));

        return self::SUCCESS;
    }

    /**
     * Every upload the card's notes use — binned notes included, since they
     * can still come back — and the photo under every drawing that is used.
     *
     * @return array<string,true>
     */
    private function inUse(Card $card): array
    {
        $used = [];
        $card->items()->withTrashed()->where('type', 'note')->pluck('body')
            ->each(function ($body) use (&$used) {
                foreach (NoteHtml::assets($body) as $uuid) {
                    $used[$uuid] = true;
                }
            });

        $byUuid = $card->assets->keyBy('uuid');
        $queue = array_keys($used);
        while ($queue) {
            $base = $byUuid->get(array_pop($queue))?->meta['ink']['base'] ?? null;
            if ($base && ! isset($used[$base])) {
                $used[$base] = true;
                $queue[] = $base;
            }
        }

        return $used;
    }

    private function dropEmptyDir(string $dir): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($dir) && ! $disk->allFiles($dir)) {
            $disk->deleteDirectory($dir);
        }
    }
}
