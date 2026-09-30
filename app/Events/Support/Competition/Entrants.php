<?php

namespace App\Events\Support\Competition;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventSide;
use Illuminate\Support\Collection;

/**
 * Who competes in a division — people, or pairs and teams — as ONE shape, so
 * every format engine (knockout, round robin, groups, swiss, heats) works for
 * both without knowing which it has.
 *
 *   key              'r12' (a registration) or 's5' (a side) — stable, unique
 *   registration_id  set for an individual
 *   side_id          set for a pair / team
 *   name, country    what a contest row and a board print
 *   seed             the organiser's seed, or null
 *
 * Only COMPLETE sides compete (a pair of one is not a pair) and never a
 * withdrawn one; only participants (not spectators) of the division.
 */
final class Entrants
{
    /** @return Collection<int, array<string, mixed>> in seed order, unseeded last */
    public function of(EventCategory $division, Entrant $kind, ?EventSides $sides = null): Collection
    {
        if ($kind === Entrant::Individual) {
            return ClubEventRegistration::where('category_id', $division->id)
                ->where('role', 'participant')
                ->with('user:id,full_name,name,nationality')
                ->orderBy('id')
                ->get()
                ->map(fn (ClubEventRegistration $r) => [
                    'key' => 'r'.$r->id,
                    'registration_id' => $r->id,
                    'side_id' => null,
                    'name' => $r->user?->full_name ?: $r->user?->name ?: __('events::events.athlete'),
                    'country' => $r->countryCode(),
                    'seed' => is_numeric(data_get((array) $r->meta, 'seed')) ? (int) data_get((array) $r->meta, 'seed') : null,
                ])
                ->sortBy(fn ($e) => [$e['seed'] ?? PHP_INT_MAX, $e['registration_id']])
                ->values();
        }

        $sides ??= app(EventSides::class);

        return EventSide::where('category_id', $division->id)
            ->whereNull('withdrawn_at')
            ->with(['tenant:id,country', 'members.registration.user:id,full_name,name'])
            ->orderBy('id')
            ->get()
            ->filter(fn (EventSide $s) => $sides->complete($s))
            ->map(fn (EventSide $s) => [
                'key' => 's'.$s->id,
                'registration_id' => null,
                'side_id' => $s->id,
                'name' => $s->displayName(),
                'country' => $s->tenant?->country,
                'seed' => $s->seed,
            ])
            ->sortBy(fn ($e) => [$e['seed'] ?? PHP_INT_MAX, $e['side_id']])
            ->values();
    }

    /** The key of whoever holds a contest's `$corner` ('a' | 'b'). */
    public static function keyOf(\App\Events\Models\EventMatch $m, string $corner): ?string
    {
        if ($id = $m->{$corner.'_side_id'}) {
            return 's'.$id;
        }

        return ($id = $m->{$corner.'_competitor_id'}) ? 'r'.$id : null;
    }

    /** The columns that put `$entrant` into `$corner` of a contest row. */
    public static function columns(?array $entrant, string $corner): array
    {
        return [
            $corner.'_name' => $entrant['name'] ?? null,
            $corner.'_country' => $entrant['country'] ?? null,
            $corner.'_competitor_id' => $entrant['registration_id'] ?? null,
            $corner.'_side_id' => $entrant['side_id'] ?? null,
            $corner.'_seed' => $entrant['seed'] ?? null,
        ];
    }
}
