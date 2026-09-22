<?php

namespace App\Events\Support;

use App\Members\Models\User;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;

/**
 * A person's own details changed — put them back on every mat and wall.
 *
 * The event desk is not the only place a competitor's name or face can change.
 * An athlete correcting their own profile, a guardian fixing a dependent's, an
 * admin editing a member from the platform screens — none of those go anywhere
 * near App\Events\Support\EntryEditor, which is where the hall was being told.
 * So a photograph replaced on a member's own profile mid-competition changed
 * the database and left the scoreboard and the upcoming board announcing the
 * old one, exactly as an organiser's edit used to.
 *
 * What makes this safe to hang off a model hook rather than a controller:
 *
 *  - It runs only when one of the fields a BOARD actually draws has changed;
 *    every other save on a user returns before a query is made.
 *  - It looks only at events that could still have a mat running. A finished
 *    championship has no screen to tell.
 *  - Everything past that point is behind App\Scoreboard\Fleet, which is
 *    silent and best-effort, and which answers by doing nothing at all when
 *    the person is in no bout. A profile edit must never fail because a
 *    television is off.
 */
class PersonChanged
{
    /**
     * The fields a mat or a board draws from the PERSON.
     *
     * Deliberately short. `birthdate` and `height_cm` reach a bout's stat line
     * and could be added, but each one widens what re-renders a hall, and this
     * list is the one that has actually gone wrong.
     *
     * @var array<int, string>
     */
    private const DRAWN = [
        'full_name', 'name', 'profile_picture', 'profile_picture_is_public',
        'nationality', 'gender',
    ];

    /** True when this save touched something a screen is showing. */
    public static function touchesAScreen(User $user): bool
    {
        foreach (self::DRAWN as $field) {
            if ($user->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refresh every mat and board this person is standing on.
     *
     * A name correction is written back onto the draw rows too — the same
     * re-stamp the entry editor does, and for the same reason: the bracket,
     * the running order and the wall all read a SNAPSHOT taken when the draw
     * was cut, so the person changing underneath it is not enough.
     */
    public static function sync(User $user): void
    {
        if (! self::touchesAScreen($user)) {
            return;
        }

        $entries = ClubEventRegistration::where('user_id', $user->id)
            ->whereIn('event_id', self::liveEventIds($user))
            ->get(['id', 'event_id']);

        if ($entries->isEmpty()) {
            return;
        }

        $name = trim((string) ($user->full_name ?: $user->name));

        foreach ($entries->groupBy('event_id') as $eventId => $rows) {
            $event = ClubEvent::find($eventId);

            if (! $event) {
                continue;
            }

            if ($name !== '' && $user->wasChanged(['full_name', 'name'])) {
                rescue(fn () => app(EntryEditor::class)->restampName($event, (int) $user->id, $name), null, false);
            }

            // One call per event, not per entry: Fleet widens to every row this
            // person holds in it anyway, so a second call is repeated work.
            rescue(fn () => app(\App\Scoreboard\Fleet::class)
                ->entryChanged($event, (int) $rows->first()->id), null, false);
        }
    }

    /**
     * Events that could still have something running.
     *
     * Today and later, not archived. A championship from March has no wall to
     * correct, and walking its draw on every profile save would be work done
     * for nobody.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private static function liveEventIds(User $user): \Illuminate\Support\Collection
    {
        return ClubEvent::query()
            ->where('is_archived', false)
            ->whereDate('date', '>=', now()->subDay()->toDateString())
            ->whereIn('id', ClubEventRegistration::where('user_id', $user->id)->select('event_id'))
            ->pluck('id');
    }
}
