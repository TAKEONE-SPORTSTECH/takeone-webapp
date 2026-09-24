<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationDraw;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Draw tab's brackets and results, kept.
 *
 * The dashboard draws a division from its entrants with its own fair-draw
 * engine and records a bout by picking the winner. This keeps what it
 * records, and keeps it honest:
 *
 *  - The first result recorded in a division LOCKS its first-round order, so
 *    the bouts already fought never move. Clearing every result unlocks it.
 *  - Every result is replayed through the bracket (the dashboard's own
 *    `buildBracket` rules) and must name one of the two athletes actually in
 *    that bout, against the same opponent it was recorded against. A changed
 *    result drops the later ones it no longer supports.
 *  - An athlete withdrawn after the lock becomes a bye; the opponent walks over.
 *
 * Any staff member who can open the event may record; scope is the viewer's
 * subtree and, for sport-limited staff, their sports.
 */
class FederationDraws
{
    public const METHODS = ['Points', 'Ippon', 'Decision', 'Disqualification', 'Withdrawal'];

    private const TBD = "\0TBD";

    public function __construct(private FederationDirectory $directory) {}

    /** Events this viewer may open. */
    private function eventsQuery(array $access)
    {
        $q = FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q;
    }

    /** `__TAKEONE_FED.draws`: event uuid => division => {slots, results}. */
    public function payload(array $access): object
    {
        $events = $this->eventsQuery($access)->pluck('uuid', 'id');
        $out = [];
        FederationDraw::whereIn('federation_event_id', $events->keys())->get()
            ->each(function (FederationDraw $d) use ($events, &$out) {
                $out[$events[$d->federation_event_id]][$d->division] = $this->shape($d);
            });

        foreach ($out as $k => $v) {
            $out[$k] = (object) $v;
        }

        return (object) $out;
    }

    private function shape(?FederationDraw $d): ?array
    {
        return $d ? ['slots' => array_values($d->slots ?? []), 'results' => (object) ($d->results ?? [])] : null;
    }

    /** Takeone IDs still competing in this event. */
    private function entrants(FederationEvent $event): Collection
    {
        return FederationEntryAthlete::query()
            ->join('federation_entries', 'federation_entries.id', '=', 'federation_entry_athletes.federation_entry_id')
            ->join('federation_athletes', 'federation_athletes.id', '=', 'federation_entry_athletes.federation_athlete_id')
            ->where('federation_entries.federation_event_id', $event->id)
            ->where('federation_entry_athletes.status', 'Active')
            ->pluck('federation_athletes.takeone_id')
            ->flip();
    }

    /**
     * Record (or with a null winner, clear) one bout.
     *
     * @return array{ok: bool, division: ?array}  the division as the server now holds it
     */
    public function record(User $user, array $access, string $eventUuid, string $division, string $match, ?string $winner, ?string $by, array $slots): array
    {
        $event = $this->eventsQuery($access)->where('uuid', $eventUuid)->first();
        if (! $event) {
            return ['ok' => false, 'division' => null];
        }

        return DB::transaction(function () use ($user, $event, $division, $match, $winner, $by, $slots) {
            $draw = FederationDraw::where('federation_event_id', $event->id)->where('division', $division)->lockForUpdate()->first();
            $entrants = $this->entrants($event);

            if ($draw) {
                $slots = $draw->slots;   // locked: what the page sent is ignored
            } elseif ($winner === null) {
                return ['ok' => true, 'division' => null];   // nothing recorded, nothing to clear
            } elseif (! $this->validSlots($slots, $entrants)) {
                return ['ok' => false, 'division' => null];
            }

            $results = $draw?->results ?? [];
            if ($winner === null) {
                unset($results[$match]);
            } else {
                $results[$match] = ['winner' => $winner, 'by' => $by];   // `against` is stamped by replay()
            }

            $results = $this->replay($slots, $results, $entrants);
            $ok = $winner === null || (($results[$match]['winner'] ?? null) === $winner);

            if (! $ok) {
                return ['ok' => false, 'division' => $this->shape($draw)];
            }

            if ($results === []) {
                $draw?->delete();

                return ['ok' => true, 'division' => null];
            }

            $draw ??= new FederationDraw(['federation_event_id' => $event->id, 'division' => $division, 'slots' => $slots]);
            $draw->results = $results;
            $draw->updated_by = $user->id;
            $draw->save();

            return ['ok' => true, 'division' => $this->shape($draw)];
        });
    }

    /** A first round the page proposes: a power of two, entrants of this event, each once. */
    private function validSlots(array $slots, Collection $entrants): bool
    {
        $n = count($slots);
        if ($n < 2 || $n > 512 || ($n & ($n - 1)) !== 0 || ! array_is_list($slots)) {
            return false;
        }
        $seen = [];
        foreach ($slots as $s) {
            if ($s === null) {
                continue;
            }
            if (! is_string($s) || ! $entrants->has($s) || isset($seen[$s])) {
                return false;
            }
            $seen[$s] = true;
        }

        return count($seen) >= 2;
    }

    /**
     * Walk the bracket exactly as the dashboard's buildBracket does and keep
     * only results naming one of the two athletes really in that bout.
     */
    private function replay(array $slots, array $results, Collection $entrants): array
    {
        $cur = array_map(fn ($s) => ($s !== null && $entrants->has($s)) ? $s : null, $slots);
        $kept = [];
        $ri = 0;
        while (count($cur) >= 2) {
            $next = [];
            for ($i = 0; $i < count($cur); $i += 2) {
                [$a, $b] = [$cur[$i], $cur[$i + 1]];
                $realA = $a !== null && $a !== self::TBD;
                $realB = $b !== null && $b !== self::TBD;
                $id = $ri.'-'.intdiv($i, 2);
                $bye = ($realA && $b === null) || ($realB && $a === null);
                $res = ($realA && $realB && isset($results[$id]) && in_array($results[$id]['winner'] ?? null, [$a, $b], true)) ? $results[$id] : null;
                if ($res) {
                    // A result is a bout between TWO people: once the other
                    // side changes (an earlier result corrected), it is gone.
                    $other = $res['winner'] === $a ? $b : $a;
                    if (isset($res['against']) && $res['against'] !== $other) {
                        $res = null;
                    } else {
                        $kept[$id] = $res + ['against' => $other];
                    }
                }
                $next[] = $bye ? ($realA ? $a : $b) : ($res ? $res['winner'] : self::TBD);
            }
            $cur = count($next) >= 2 ? $next : [];
            $ri++;
        }

        return $kept;
    }
}
