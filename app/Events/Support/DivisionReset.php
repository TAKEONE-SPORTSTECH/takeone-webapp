<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starting the divisions again: delete every division, and place every entrant
 * in the division the rule book puts them in — creating those divisions.
 *
 * The organiser's workflow (owner, 2026-10-15): delete all → auto-place →
 * merge the small ones (DivisionMerge). Auto-place only creates a division
 * somebody actually fits, so no empty group is left behind.
 *
 * ── What is never touched ──────────────────────────────────────────────────
 *  · Nothing once a bout has been fought or filmed, or the event has started —
 *    the same line deleting one division draws.
 *  · An event entered in separate PARTS (Kata · Kumite, Gi · No-Gi): which
 *    part an entry belongs to is carried by its division, so deleting them
 *    would forget it. Those are rebuilt by hand.
 *  · Auto-place never moves anyone a person placed by hand; it places only
 *    entrants in no division. A person with several entries is placed once —
 *    the extra rows stay as they are and are reported.
 *
 * ── Recoverable ───────────────────────────────────────────────────────────
 * Delete-all writes the layout it removed (every division's settings and who
 * was in it) into the activity log row it records, so a mistake can be rebuilt
 * from the record rather than from memory.
 *
 * Classification is the platform's own: CategorySystems::placeCreating, the
 * same call a federation's entries are placed with, under the event's rule
 * book (CategorySystems::sportFor). Nothing here knows which sport it sorts.
 */
class DivisionReset
{
    public function __construct(private CategorySystems $systems, private EventTypeRegistry $registry) {}

    /** Switched on with the division tools (EVENT_DIVISION_MERGE). */
    public static function enabled(): bool
    {
        return DivisionMerge::enabled();
    }

    /** Why the divisions of this event may not be reset, or null when they may. */
    public function refusal(ClubEvent $event): ?string
    {
        if ($event->hasStarted() || $event->hasEnded()) {
            return __('events::events.reset_started');
        }
        if (EventStreams::many($event)) {
            return __('events::events.reset_streams');
        }
        $bouts = EventMatch::where('event_id', $event->id)->pluck('id');
        if (EventMatch::where('event_id', $event->id)->contested()->exists()
            || ($bouts->isNotEmpty() && (DB::table('event_recordings')->whereIn('match_id', $bouts)->exists()
                || DB::table('event_camera_clips')->whereIn('match_id', $bouts)->exists()))) {
            return __('events::events.reset_fought');
        }

        return null;
    }

    /**
     * Delete every division (headings too) and its unfought draw. Every entry
     * stays in the event, in no division.
     *
     * @return array{ok:bool, message:string, deleted?:int, released?:int}
     */
    public function deleteAll(ClubEvent $event, User $by): array
    {
        if ($why = $this->refusal($event)) {
            return ['ok' => false, 'message' => $why];
        }

        $cats = EventCategory::where('event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $snapshot = $cats->map(fn (EventCategory $c) => $c->only(['id', 'name', 'is_heading', 'weight_class', 'gender', 'min_age', 'max_age',
            'min_weight', 'max_weight', 'belts', 'capacity', 'format', 'schedule', 'stream', 'sort_order']) + [
                'entries' => ClubEventRegistration::where('event_id', $event->id)->where('category_id', $c->id)->pluck('id')->all(),
            ])->all();

        $released = 0;
        DB::transaction(function () use ($event, &$released) {
            EventMatch::where('event_id', $event->id)->delete();
            $released = ClubEventRegistration::where('event_id', $event->id)->whereNotNull('category_id')
                ->update(['category_id' => null, 'category_source' => null]);
            EventCategory::where('event_id', $event->id)->delete();
        });

        rescue(fn () => activity()->causedBy($by)->withProperties(['event' => $event->uuid, 'divisions' => $snapshot])
            ->log('events.divisions_deleted_all'), null, true);

        return ['ok' => true, 'deleted' => $cats->count(), 'released' => $released,
            'message' => __('events::events.reset_deleted', ['count' => $cats->count(), 'people' => $released])];
    }

    /**
     * Place everybody in no division where the rule book puts them, creating
     * the divisions needed. When the event had no divisions at all, the new
     * ones are put in reading order: gender, then age group, then weight.
     *
     * @return array{ok:bool, message:string, placed?:int, created?:int, left?:int}
     */
    public function autoPlace(ClubEvent $event, User $by, ?string $system = null): array
    {
        if ($event->hasStarted() || $event->hasEnded()) {
            return ['ok' => false, 'message' => __('events::events.reset_started')];
        }
        if (EventStreams::many($event)) {
            return ['ok' => false, 'message' => __('events::events.reset_streams')];
        }
        if ($system === self::OWN) {
            // The choice is kept: from now on every automatic placement on this
            // event fills these divisions (OwnDivisions), until they choose again.
            $event->forceFill(['division_source' => 'own'])->save();

            return $this->placeIntoOwn($event, $by);
        }

        /*
         * The weight table the organiser picked for THIS placing (owner,
         * 2026-10-15: "auto-place must obey a specific martial art's weight
         * filter"). It becomes the event's rule book through the one door that
         * changes it — CategorySystems::change, the same as the rule-book row
         * in the console — which re-sorts anybody the platform placed and
         * refuses once a draw has been cut. Hand placements never move.
         */
        if ($system !== null && $system !== '') {
            if (! array_key_exists($system, $this->systems->weightsFor($event))) {
                return ['ok' => false, 'message' => __('events::events.rulebook_invalid')];
            }
            $current = $this->systems->forEvent($event);
            // Already the table in force (chosen, or the sport's default): nothing to change.
            if (! $current || $current->key !== $system) {
                $changed = $this->systems->change($event, $system, $event->belt_system);
                if (! $changed['ok']) {
                    return ['ok' => false, 'message' => $changed['message'] ?? __('events::events.rulebook_locked')];
                }
                $event = $event->fresh();
            }
            // A table was chosen: kept, so the event follows it from now on.
            $event->forceFill(['division_source' => 'table'])->save();
        }

        $hadNone = ! EventCategory::where('event_id', $event->id)->exists();
        $before = EventCategory::where('event_id', $event->id)->pluck('id')->all();

        $entries = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->with('user')->orderBy('id')->get();
        $placedUsers = $entries->whereNotNull('category_id')->pluck('user_id')->flip();

        $placed = 0;
        $left = 0;
        $touched = [];
        foreach ($entries->whereNull('category_id')->groupBy('user_id') as $userId => $rows) {
            if (isset($placedUsers[$userId])) {
                continue;                       // already competing on another entry
            }
            // Placed by the weigh-in weight when there is one — the authentic
            // one (owner, 2026-10-15) — never a declared weight it replaced.
            if (\App\Events\Support\BodyWeight::spread($event->id, (int) $userId)) {
                $rows = $rows->map->fresh();
            }
            $reg = $rows->first();
            // The organiser picked a weight table in the popup: that choice stands.
            $cat = rescue(fn () => $this->systems->placeCreating($event, $reg, ruleBook: true), null, true);
            if ($cat) {
                $placed++;
                $touched[$cat->id] = $cat;
            } else {
                $left++;
            }
        }

        $created = EventCategory::where('event_id', $event->id)->whereNotIn('id', $before)->count();

        if ($hadNone && $created > 1) {
            $this->readingOrder($event);
        }

        $type = $this->registry->for($event);
        foreach ($touched as $cat) {
            $cat->setRelation('event', $event);
            rescue(fn () => $type->onEntrantsChanged($event, $cat), null, true);
        }

        rescue(fn () => activity()->causedBy($by)->withProperties(['event' => $event->uuid, 'placed' => $placed, 'created' => $created, 'left' => $left])
            ->log('events.divisions_auto_placed'), null, true);

        return ['ok' => true, 'placed' => $placed, 'created' => $created, 'left' => $left,
            'message' => __('events::events.reset_placed', ['placed' => $placed, 'created' => $created])
                .($left ? ' '.__('events::events.reset_left', ['count' => $left]) : '')];
    }

    /** The picker's key for "the divisions this event already has". */
    public const OWN = 'event-own';

    /**
     * Place everybody in no division into the event's OWN divisions — the ones
     * the organiser made by hand — by each division's gender, age and weight
     * range (owner, 2026-10-15). Never creates, renames or empties a division,
     * and never touches the rule book. The fit is UnplacedEntrants' (the
     * "Not in a division" list): the tightest division they fit outright, by
     * the weigh-in weight where there is one (BodyWeight). Anyone who fits
     * none, or is missing what a division asks for, is left for that list.
     *
     * @return array{ok:bool, message:string, placed?:int, created?:int, left?:int}
     */
    private function placeIntoOwn(ClubEvent $event, User $by): array
    {
        if (! $event->categories()->competing()->exists()) {
            return ['ok' => false, 'message' => __('events::events.reset_own_none')];
        }

        $people = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->whereNull('category_id')->distinct()->pluck('user_id');
        foreach ($people as $userId) {
            \App\Events\Support\BodyWeight::spread($event->id, $userId ? (int) $userId : null);
        }

        $placed = 0;
        $left = 0;
        $touched = [];
        foreach (app(UnplacedEntrants::class)->for($event) as $row) {
            $cat = $row['suggested'] ? EventCategory::where('event_id', $event->id)->find($row['suggested']) : null;
            $reg = $cat ? ClubEventRegistration::where('event_id', $event->id)->whereNull('category_id')->find($row['entry']) : null;
            if (! $reg) {
                $left++;

                continue;
            }
            $reg->forceFill(['category_id' => $cat->id, 'category_source' => ClubEventRegistration::PLACED_AUTO])->save();
            $placed++;
            $touched[$cat->id] = $cat;
        }

        $type = $this->registry->for($event);
        foreach ($touched as $cat) {
            $cat->setRelation('event', $event);
            rescue(fn () => $type->onEntrantsChanged($event, $cat), null, true);
        }

        rescue(fn () => activity()->causedBy($by)->withProperties(['event' => $event->uuid, 'placed' => $placed, 'left' => $left, 'into' => 'own'])
            ->log('events.divisions_auto_placed'), null, true);

        return ['ok' => true, 'placed' => $placed, 'created' => 0, 'left' => $left,
            'message' => __('events::events.reset_placed_own', ['placed' => $placed])
                .($left ? ' '.__('events::events.reset_left_own', ['count' => $left]) : '')];
    }

    /**
     * The weight tables this event can be placed by — its sport's own
     * (WKF, Shotokan, World Taekwondo, IBJJF…) and its host's custom ones —
     * each summarised for a picker, the one in force marked.
     *
     * @return array{sport:?string, current:?string, systems:list<array<string,mixed>>}
     */
    public function systems(ClubEvent $event): array
    {
        $all = $this->systems->weightsFor($event);
        $current = $this->systems->forEvent($event)?->key;
        $sport = rescue(fn () => app(\App\Sports\Combat\SportRegistry::class)->get((string) $event->sport)?->label(), null, false);

        // The divisions this event already has come first, and are the default
        // pick whenever there are any (owner, 2026-10-15: auto-place must fill
        // the divisions the organiser made).
        $mine = $event->categories()->competing()->orderBy('sort_order')->orderBy('id')->pluck('name');
        $own = $mine->isEmpty() || EventStreams::many($event) ? [] : [[
            'key' => self::OWN, 'label' => __('events::events.autoplace_event_own'),
            'note' => __('events::events.autoplace_event_own_note', ['n' => $mine->count()]),
            'custom' => false, 'own' => true, 'current' => ($event->division_source ?? null) === 'own',
            'hint' => __('events::events.autoplace_event_own_hint'),
            'groups' => $mine->map(fn ($n) => (string) $n)->all(), 'classes' => 0,
        ]];

        // What the picker starts on is what the organiser last CHOSE, kept
        // (club_events.division_source); never chosen, their own divisions.
        $source = $event->division_source ?? null;
        $pick = match (true) {
            $source === 'own' && $own !== [] => self::OWN,
            $source === 'table' => $current,
            default => $own ? self::OWN : $current,
        };
        // A table reads "in use" only while the event follows a table.
        $current = $source === 'own' ? null : $current;

        return [
            'sport' => $sport ?: ($event->sport ? ucfirst((string) $event->sport) : null),
            'current' => $current,
            'source' => $source,
            'pick' => $pick,
            'systems' => array_merge($own, array_values(array_map(function ($s) use ($current) {
                $groups = array_map(fn ($g) => $g['key'].' '.(($g['max'] ?? null) === null ? ($g['min'] ?? 0).'+' : ($g['min'] ?? 0).'–'.$g['max']), $s->ageGroups);
                $classes = 0;
                foreach ((array) $s->classes as $byGender) {
                    foreach ((array) $byGender as $list) {
                        $classes += count((array) $list);
                    }
                }

                return ['key' => $s->key, 'label' => $s->label, 'note' => $s->note, 'custom' => $s->custom,
                    'current' => $s->key === $current, 'groups' => $groups, 'classes' => $classes];
            }, $all))),
        ];
    }

    /** Male then female, youngest group first, lightest class first. */
    private function readingOrder(ClubEvent $event): void
    {
        $cats = EventCategory::where('event_id', $event->id)->get()->sortBy(fn (EventCategory $c) => [
            strtolower((string) $c->gender) === 'female' ? 1 : 0,
            $c->min_age === null ? 999 : (int) $c->min_age,
            $c->max_age === null ? 999 : (int) $c->max_age,
            $c->max_weight === null || (float) $c->max_weight <= 0 || (float) $c->max_weight >= 200 ? 999 : (float) $c->max_weight,
            (float) ($c->min_weight ?? 0),
        ])->values();

        foreach ($cats as $i => $c) {
            $c->forceFill(['sort_order' => $i + 1])->save();
        }
    }
}
