<?php

namespace App\DevBoard\Support;

use App\DevBoard\Mail\TakeNoteInvite;
use App\DevBoard\Models\Notebook;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Support\Facades\Mail;

/**
 * Sending a TakeNote invitation: a notebook of their own waiting for the address
 * (or re-opened, their notes still in it), an email in their language, and a
 * notice in TAKEONE when they already have an account. ONE place, used by the
 * friends sheet and by sharing a folder with somebody who has no TakeNote yet.
 * Who may invite, and whether this address may be, is the caller's to decide.
 */
final class FriendInvites
{
    public const MAX = 100;

    /** The waiting notebook, or null when the friends' cap is reached. */
    public static function open(string $email, User $by, ?User $account): ?Notebook
    {
        $book = Notebook::where('is_main', false)
            ->where(fn ($q) => $q->where('email', $email)->when($account, fn ($q) => $q->orWhere('owner_id', $account->id)))
            ->first();
        if (! $book && Notebook::where('is_main', false)->count() >= self::MAX) {
            return null;
        }

        if ($book) {
            $book->forceFill(['revoked_at' => null, 'invited_by' => $by->id])->save(); // invited again: their notes are still there
        } else {
            $book = Notebook::create(['email' => $email, 'invited_by' => $by->id]);
        }

        // an email to the address invited, in their language if their account has one, else the inviter's;
        // queued, and a failed send never fails the invitation (the sheet also offers the link to copy)
        $lang = in_array((string) $account?->locale, array_keys(config('devboard.locales', [])), true) ? $account->locale : app()->getLocale();
        rescue(fn () => Mail::to($email)->queue(
            (new TakeNoteInvite(self::first($by), $email, route('takenote.app')))->locale($lang)
        ), report: true);

        // and told in TAKEONE if they already have an account; the answer is the same either way
        if ($account && $account->hasVerifiedEmail()) {
            UserNotification::notifyUser($account->id, 'takenote_invite', __('devboard::messages.fr_notice', ['name' => self::first($by)]), [
                'actor_id' => $by->id,
                'icon' => 'bi-journal-text',
                'action_url' => route('takenote.app'),
            ]);
        }

        return $book;
    }

    public static function first(User $u): string
    {
        return trim(explode(' ', trim((string) $u->full_name))[0] ?? '') ?: (string) $u->full_name;
    }
}
