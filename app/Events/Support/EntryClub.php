<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The club an ENTRY competes for — whichever of the two kinds it is.
 *
 * There are two, and every surface that prints a club beside a competitor has
 * to know both:
 *
 *  1. A TENANT — a club with an account on the platform, reached through
 *     ClubEventRegistration::competingClub().
 *  2. An EVENT CLUB — a name and a crest the organiser wrote down on the day
 *     for a team the platform has never met (`event_clubs`, linked to the entry
 *     through `event_club_entrants`). Most of a real competition is these.
 *
 * This class exists because only the first was ever visible from the mat. The
 * entrants list resolved both — inside a private controller method — while the
 * scoring table's running order and the wall's introduction knew only about
 * tenants, so an athlete written down under an event club appeared with no club
 * and no crest on the two screens the hall actually looks at. Same rule, two
 * implementations, and one of them incomplete: exactly what the "Shared Stays
 * Shared" rule is about.
 *
 * A tenant WINS when the entry names one: it is the stronger claim (it was
 * accepted by a club that exists) and it carries a country and a page.
 */
class EntryClub
{
    /**
     * One entry's club, or null when it competes for nobody.
     *
     * @return array{name: string, logo: ?string, country: ?string}|null
     */
    public static function for(?ClubEventRegistration $registration): ?array
    {
        if (! $registration) {
            return null;
        }

        if ($tenant = $registration->competingClub()) {
            return [
                'name' => (string) $tenant->club_name,
                'logo' => $tenant->logo ? file_url($tenant->logo) : null,
                'country' => $tenant->country ?: null,
            ];
        }

        return static::forMany($registration->event_id, [$registration->id])[$registration->id] ?? null;
    }

    /**
     * The written-down clubs for a set of entries, keyed by registration id.
     *
     * One query rather than one per competitor — a running order draws a dozen
     * rows and a hall screen redraws on every state change.
     *
     * @param  iterable<int>  $registrationIds
     * @return array<int, array{name: string, logo: ?string, country: ?string}>
     */
    public static function forMany(int|ClubEvent $event, iterable $registrationIds): array
    {
        $eventId = $event instanceof ClubEvent ? $event->id : $event;

        $ids = collect($registrationIds)->filter()->unique()->values()->all();

        // The table is guarded rather than assumed: this runs on a mat, and a
        // scoreboard that fatals because a migration has not been applied is a
        // dead screen in a hall.
        if (! $ids || ! Schema::hasTable('event_club_entrants') || ! Schema::hasTable('event_clubs')) {
            return [];
        }

        $found = DB::table('event_club_entrants as l')
            ->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
            ->where('c.event_id', $eventId)
            ->whereIn('l.registration_id', $ids)
            ->select('l.registration_id', 'c.name', 'c.logo', 'c.country')
            ->get()
            ->keyBy('registration_id')
            ->map(fn ($row) => static::shape($row))
            ->all();

        return $found + static::byPerson($eventId, array_values(array_diff($ids, array_keys($found))));
    }

    /**
     * The club a PERSON is at this event with, for entries nobody linked.
     *
     * `event_club_entrants` is keyed by ENTRY, and an athlete has one entry per
     * division — so an organiser who writes a competitor down under a club
     * while editing one of their entries leaves the others attached to nothing.
     * The same athlete then read as "BAHRAIN FIGHTERS" in one division and as
     * nobody in the next, on the same wall, minutes apart. At the Victory BJJ
     * Championship that was most of the field: 33 entries linked out of the 71
     * belonging to a written-down club.
     *
     * A person belongs to ONE club at ONE competition, so the club travels with
     * them across their entries here — but only when it is UNAMBIGUOUS. An
     * athlete linked to two different clubs in the same event is a question
     * only the organiser can answer, and this resolves them to nothing rather
     * than picking one and printing it on a wall.
     *
     * It fills blanks and never overrides: a link on the entry itself, and a
     * tenant named by the entry, both still win.
     *
     * @param  array<int, int>  $unresolved
     * @return array<int, array{name: string, logo: ?string, country: ?string}>
     */
    private static function byPerson(int $eventId, array $unresolved): array
    {
        if (! $unresolved) {
            return [];
        }

        // Who those entries belong to. A claim with no account yet has no
        // user_id and simply has no other entry to borrow from.
        $people = DB::table('club_event_registrations')
            ->whereIn('id', $unresolved)
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        if ($people->isEmpty()) {
            return [];
        }

        $clubs = DB::table('event_club_entrants as l')
            ->join('event_clubs as c', 'c.id', '=', 'l.event_club_id')
            ->join('club_event_registrations as r', 'r.id', '=', 'l.registration_id')
            ->where('c.event_id', $eventId)
            ->where('r.event_id', $eventId)
            ->whereIn('r.user_id', $people->unique()->values()->all())
            ->select('r.user_id', 'c.id as club_id', 'c.name', 'c.logo', 'c.country')
            ->get()
            ->groupBy('user_id');

        $out = [];

        foreach ($people as $registrationId => $userId) {
            $rows = $clubs->get($userId);

            // None, or more than one club — say nothing.
            if (! $rows || $rows->pluck('club_id')->unique()->count() !== 1) {
                continue;
            }

            $out[(int) $registrationId] = static::shape($rows->first());
        }

        return $out;
    }

    /**
     * One `event_clubs` row as the display shape every caller expects.
     *
     * @return array{name: string, logo: ?string, country: ?string}
     */
    private static function shape(object $row): array
    {
        return [
            'name' => (string) $row->name,
            'logo' => $row->logo ? file_url($row->logo) : null,
            'country' => $row->country ?: null,
        ];
    }
}
