<?php

namespace App\DevBoard\Support;

use App\DevBoard\Models\BoardMember;
use App\Members\Models\User;

/**
 * Who may open the board. One place, asked by every route, the capture button
 * and the MCP tools alike.
 *
 * The super-admin and the people in devboard_members may (the owner's main
 * notebook), and so may a friend the owner invited (a notebook of their own —
 * Support\Notebooks). Deny by default: flag off, signed out, or not listed → no.
 */
final class BoardAccess
{
    public static function enabled(): bool
    {
        return (bool) config('devboard.enabled');
    }

    public static function allows(?User $user): bool
    {
        if (! self::enabled() || ! $user) {
            return false;
        }

        // the main notebook's people (members and super-admins, as before), or an invited friend with a notebook of their own
        return Notebooks::for($user) !== null;
    }

    /**
     * Whether the app's menus show the way in. Same answer as allows(), asked
     * once per request however many menus a page draws, and never when the
     * routes are not registered (flag off).
     */
    public static function linkFor(?User $user): bool
    {
        if (! $user || ! \Illuminate\Support\Facades\Route::has('me.margin')) {
            return false;
        }

        $memo = request()->attributes;
        $key = 'devboard.link.'.$user->id;
        if (! $memo->has($key)) {
            $memo->set($key, self::allows($user));
        }

        return $memo->get($key);
    }

    /** The ink a person's work is drawn in; the super-admin defaults to the first. */
    public static function ink(User $user): string
    {
        return BoardMember::where('user_id', $user->id)->value('ink')
            ?? config('devboard.inks.0', '#8b7cf6');
    }
}
