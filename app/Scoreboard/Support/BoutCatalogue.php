<?php

namespace App\Scoreboard\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Support\EntryClub;

/**
 * The WHOLE draw of one event, for a scoring console's browsing tabs — READ ONLY.
 *
 * The console's running order answers "what is next on this mat". This answers
 * the other question an official at a table is asked: which weight class,
 * which person, or these two standing here. The console fetches it once and
 * searches it locally (weight class · member · arcade).
 *
 * Sport-neutral on purpose (CLAUDE.md → Shared Stays Shared): nothing here
 * knows which sport's mat asked. It was built for the Karate console as a port
 * of the BJJ console's own catalogue, which still lives inside the BJJ
 * controller — pointing BJJ at this class is registered in
 * Documentation/HOUSE-CLEANING.md rather than done in passing (RULE #1).
 *
 * ── Shape ─────────────────────────────────────────────────────────────────
 * The roster is keyed ONCE by registration id and a bout carries only the two
 * ids: an athlete fights several times, and repeating their face, club and
 * flag inside every bout row multiplied the payload by the length of their run.
 *
 * It writes nothing and decides nothing. Loading one of these bouts is the
 * console's existing `load` command, which re-checks the draw itself.
 * Authorisation is the caller's — the same canScore() the console page and
 * every command already check. The roster carries each entrant's gender and
 * recorded weight for the Arcade tab, so it must never be served to anyone
 * less than a scoring official.
 */
class BoutCatalogue
{
    /**
     * @return array{divisions: array, roster: array, bouts: array}
     */
    public static function for(ClubEvent $event): array
    {
        $matches = EventMatch::where('event_id', $event->id)
            ->with('category:id,name,weight_class,is_heading,sort_order')
            ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('match_no')
            ->orderBy('id')
            ->get();

        $entryIds = $matches->flatMap(fn (EventMatch $m) => [$m->a_competitor_id, $m->b_competitor_id])
            ->filter()->unique()->values()->all();

        $sideOf = self::sideBuilder($event, $entryIds);

        // The names live on the MATCH, because that is what the draw froze and
        // what the wall prints. The roster's own name is only the fallback.
        $names = [];
        foreach ($matches as $m) {
            if ($m->a_competitor_id && ! isset($names[$m->a_competitor_id])) {
                $names[$m->a_competitor_id] = $m->a_name;
            }
            if ($m->b_competitor_id && ! isset($names[$m->b_competitor_id])) {
                $names[$m->b_competitor_id] = $m->b_name;
            }
        }

        // The weight class(es) each entrant is drawn in, read off their bouts —
        // a person can be in more than one division of the same event.
        $classes = [];
        foreach ($matches as $m) {
            $label = $m->category && ! $m->category->isHeading()
                ? (string) ($m->category->weight_class ?: $m->category->name) : null;
            foreach ([$m->a_competitor_id, $m->b_competitor_id] as $cid) {
                if ($cid && $label !== null && ! in_array($label, $classes[$cid] ?? [], true)) {
                    $classes[$cid][] = $label;
                }
            }
        }

        $roster = [];
        foreach ($entryIds as $id) {
            $roster[(string) $id] = ['name' => ($names[$id] ?? null) ?: null]
                + $sideOf($id)
                + ['divisions' => $classes[$id] ?? []];
        }

        // Only divisions that hold a bout, and never a HEADING — a heading is a
        // title in the organiser's list and holds nobody.
        $divisions = [];
        foreach ($matches as $m) {
            $c = $m->category;
            if (! $c || $c->isHeading()) {
                continue;
            }

            $key = (string) $c->id;
            $divisions[$key] ??= [
                'id' => $c->id,
                // The same label the running order and the board print.
                'label' => (string) ($c->weight_class ?: $c->name),
                'name' => (string) $c->name,
                'sort' => (int) ($c->sort_order ?? 0),
                'bouts' => 0,
                'done' => 0,
            ];

            $divisions[$key]['bouts']++;
            if (self::isDone($m)) {
                $divisions[$key]['done']++;
            }
        }

        $divisions = collect($divisions)->sortBy([['sort', 'asc'], ['label', 'asc']])->values()->all();

        return [
            'divisions' => $divisions,
            'roster' => $roster,
            'bouts' => $matches->map(fn (EventMatch $m) => [
                'id' => $m->id,
                'number' => $m->match_no,
                'stage' => $m->round ?: $m->phase,
                'division_id' => $m->category?->id,
                'division' => $m->category?->weight_class ?: $m->category?->name,
                'court' => $m->court,
                'a' => $m->a_competitor_id,
                'b' => $m->b_competitor_id,
                // A corner the draw named but has no entry row for — written in
                // on the day — still has to read on the sheet.
                'a_name' => $m->a_name ?: null,
                'b_name' => $m->b_name ?: null,
                'done' => self::isDone($m),
                'runnable' => (bool) ($m->a_competitor_id && $m->b_competitor_id),
            ])->values()->all(),
        ];
    }

    private static function isDone(EventMatch $m): bool
    {
        return (bool) ($m->winner || $m->status === 'done');
    }

    /**
     * One corner as every console list prints it: face, club, crest, flag.
     *
     * The same rules the consoles' running orders use — the club they COMPETE
     * FOR (a tenant first, then the club the organiser wrote down), the flag
     * through ClubEventRegistration::countryCode(), the event's own photo, and a
     * member's profile picture only when they made it public.
     *
     * @param  array<int, int>  $entryIds
     * @return callable(?int): array<string, mixed>
     */
    private static function sideBuilder(ClubEvent $event, array $entryIds): callable
    {
        $entries = $entryIds
            ? ClubEventRegistration::where('event_id', $event->id)
                ->whereIn('id', $entryIds)
                // `nationality` and `logo` are load-bearing: a constrained eager
                // load that omits a column makes every read of it silently null.
                ->with(['user:id,full_name,name,gender,nationality,profile_picture,profile_picture_is_public',
                    'user.memberClubs:id,club_name,logo,country',
                    'representingTenant:id,club_name,logo,country'])
                ->get()->keyBy('id')
            : collect();

        $written = EntryClub::forMany($event, $entryIds);

        return function (?int $id) use ($entries, $written): array {
            $reg = $id ? $entries->get($id) : null;
            $club = $reg?->competingClub();
            $user = $reg?->user;

            $named = $club
                ? ['name' => $club->club_name, 'logo' => $club->logo ? file_url($club->logo) : null, 'country' => $club->country]
                : ($id ? ($written[$id] ?? null) : null);

            $iso = collect([$named['country'] ?? null, $reg?->countryCode()])
                ->first(fn ($c) => preg_match('/^[A-Za-z]{2}$/', (string) $c));

            return [
                'club' => $named['name'] ?? '',
                'logo' => $named['logo'] ?? null,
                'flag' => $iso ? strtolower((string) $iso) : null,
                'photo' => $reg?->photo
                    ? file_url($reg->photo)
                    : (($user?->profile_picture && $user->profile_picture_is_public)
                        ? file_url($user->profile_picture)
                        : null),
                'fallback' => \App\Members\Support\Avatar::placeholder($user?->gender),
                // What an official checks before calling two people up: the
                // canonical Male/Female (or null), and the weight recorded on
                // the entry — the weigh-in's figure, in kg. This door is behind
                // canScore(), the same officials who weigh people in.
                'gender' => in_array($user?->gender, ['Male', 'Female'], true) ? $user->gender : null,
                'weight' => $reg?->weight !== null ? (float) $reg->weight : null,
            ];
        };
    }
}
