<?php

namespace App\DevBoard\Support;

use Illuminate\Support\Facades\DB;

/**
 * How much one notebook holds, and whether a new file still fits.
 *
 * Every picture, drawing, voice note, video and attached file counts — the
 * bin's included, because their bytes are still on disk until the bin is
 * emptied. Read straight from the tables (past the notebook scope), so the
 * answer is the notebook's whole, whoever asks. A file put into a SHARED
 * folder lives in the owner's notebook and counts against the owner.
 */
final class Storage
{
    public static function limit(): int
    {
        return max(1, (int) config('devboard.quota_mb', 1024)) * 1024 * 1024;
    }

    public static function used(int $notebookId): int
    {
        $cards = fn ($q) => $q->select('id')->from('devboard_cards')->where('notebook_id', $notebookId);

        return (int) DB::table('devboard_assets')->whereIn('card_id', $cards)->sum('bytes')
            + (int) DB::table('devboard_attachments')->whereIn('card_id', $cards)->sum('size');
    }

    /** Whether $bytes more still fit in this notebook. */
    public static function fits(int $notebookId, int $bytes): bool
    {
        return self::used($notebookId) + max(0, $bytes) <= self::limit();
    }

    /** @return array{used:int,limit:int} */
    public static function summary(int $notebookId): array
    {
        return ['used' => self::used($notebookId), 'limit' => self::limit()];
    }

    /** Refuses an upload that would not fit: the same answer, in the person's language, from every door. */
    public static function guard(int $notebookId, int $bytes): void
    {
        abort_unless(self::fits($notebookId, $bytes), 422, __('devboard::messages.st_full'));
    }
}
