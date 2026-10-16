<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The entrants of an event who are in NO division — and, for each, the
 * divisions they could be placed in, best first.
 *
 * "In no division" means a person with at least one participant entry in the
 * event and none of those entries in a group: invisible to every board until
 * somebody places them. A person who is already in one group and holds a
 * second, unplaced row is NOT listed — they are competing.
 *
 * Fit is DivisionRange's own verdict (in / unknown / out), the same one the
 * division editor's people tab and the participant card's enrolment sheet use,
 * so the three can never disagree. The suggestion is the tightest group that
 * fits outright: with open-bottom weight classes (−30, −40, −45 all starting
 * at 0) a 22 kg child "fits" all three, and the one they belong in is the
 * narrowest.
 *
 * Reads only. Placing goes through PersonalEventController::updateDivisionMembers
 * (me.events.divisions.members), which authorises and records it.
 */
class UnplacedEntrants
{
    /** @return list<array<string, mixed>> */
    public function for(ClubEvent $event): array
    {
        $rows = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,uuid,name,full_name,gender,birthdate', 'representingTenant:id,name'])
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $unplaced = $rows->filter(fn ($entries) => $entries->every(fn ($r) => $r->category_id === null));
        if ($unplaced->isEmpty()) {
            return [];
        }

        $categories = $event->categories()->competing()->orderBy('sort_order')->orderBy('id')->get()
            ->each(fn (EventCategory $c) => $c->setRelation('event', $event));

        // The federation's athlete number, so a federation page can match its own rows.
        $takeone = DB::table('federation_athletes')->whereIn('user_id', $unplaced->keys())->pluck('takeone_id', 'user_id');
        $on = $event->date ? Carbon::parse($event->date) : null;

        return $unplaced->map(function ($entries, $userId) use ($categories, $takeone, $on) {
            /** @var ClubEventRegistration $entry */
            $entry = $entries->first();
            $user = $entry->user;
            $born = $user?->birthdate ? Carbon::parse($user->birthdate) : null;

            $options = $categories->map(function (EventCategory $c) use ($entry) {
                $j = $c->range()->judge($entry);
                $w = ($c->max_weight !== null && (float) $c->max_weight > 0 ? (float) $c->max_weight : 300)
                    - ($c->min_weight !== null ? (float) $c->min_weight : 0);
                $a = ($c->max_age !== null ? (int) $c->max_age : 100) - ($c->min_age !== null ? (int) $c->min_age : 0);

                return ['id' => (int) $c->id, 'name' => (string) $c->name, 'fit' => $j['fit'],
                    'misses' => $j['misses'], 'width' => [$w, $a]];
            })->filter(fn ($o) => $o['fit'] !== 'out')
                ->sortBy(fn ($o) => [$o['fit'] === 'in' ? 0 : 1, $o['width'][0], $o['width'][1]])
                ->values();

            $best = $options->firstWhere('fit', 'in');

            return [
                'entry' => (int) $entry->id,
                'uuid' => $user?->uuid,
                'takeone_id' => $takeone[$userId] ?? null,
                'name' => (string) ($user?->full_name ?: $user?->name ?: '—'),
                'gender' => in_array(strtolower((string) $user?->gender), ['male', 'female'], true) ? ucfirst(strtolower((string) $user->gender)) : null,
                'age' => $born ? (int) floor($born->diffInYears($on ?? now())) : ($entry->declared_age !== null ? (int) $entry->declared_age : null),
                'weight' => $entry->weight !== null && (float) $entry->weight > 0 ? (float) $entry->weight : null,
                'club' => $entry->representingTenant?->name,
                'suggested' => $best['id'] ?? null,
                'options' => $options->map(fn ($o) => ['id' => $o['id'], 'name' => $o['name'], 'fit' => $o['fit']])->all(),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
