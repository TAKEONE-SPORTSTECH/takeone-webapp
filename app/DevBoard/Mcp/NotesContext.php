<?php

namespace App\DevBoard\Mcp;

use App\DevBoard\Support\BoardAccess;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;

/**
 * The notebook an MCP notes tool works in — the same one /me/notes opens for
 * this person, with the same gates (TakeNote on, a verified email or a friend
 * who came in by link, a notebook of their own or the main one's people).
 *
 * Every card and folder query is scoped by Notebooks' global scope, and that
 * scope only applies once a notebook is SET — with none set, a query would see
 * every notebook. So run() sets it before the callback and always clears it
 * after, and refuses outright when the person has no notebook.
 */
final class NotesContext
{
    /** Why this person cannot use notes over MCP, or null when they can. */
    public static function refusal(User $user): ?string
    {
        if (! BoardAccess::enabled() || ! config('devboard.takenote')) {
            return 'Notes are not enabled on this server.';
        }
        if (! $user->hasVerifiedEmail() && ! Notebooks::joinedByLink($user)) {
            return 'Confirm your email address before using notes.';
        }
        if (! Notebooks::for($user)) {
            return 'This account has no notebook.';
        }

        return null;
    }

    /**
     * Run $work inside the person's own notebook (no shared-folder fence — the
     * notebook /me/notes opens by default).
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function run(User $user, callable $work): mixed
    {
        $book = Notebooks::for($user);
        abort_unless($book !== null, 404);

        Notebooks::set($book);
        try {
            return $work();
        } finally {
            Notebooks::set(null);
        }
    }
}
