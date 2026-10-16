<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventRunSlot;
use Illuminate\Support\Collection;

/**
 * GROUPS on a running order, and how each one calls its bouts.
 *
 * The organiser puts weight classes into groups on the day board: a GROUP slot
 * (`EventRunSlot::KIND_GROUP`) is a box, and a class belongs to it by naming it
 * (`group_id`). A group finishes ALL of its bouts before the next item on the
 * day starts, and calls them by its own method:
 *
 *   class  — ONE CLASS AT A TIME. Each class in the group runs from its first
 *            bout to its final before the next class starts.
 *   round  — ROUND BY ROUND (World Taekwondo). Every class in the group fights
 *            its earliest round, then every class its next, the finals last
 *            and together. Athletes rest between bouts.
 *   manual — the group keeps the order its bouts already have; the organiser
 *            moves them on the bout-order board (OrderBoard), and the scheduler
 *            leaves that order alone. A bout nobody has placed yet (a late
 *            entry) joins the end of the group.
 *
 * Groups may sit back to back. A break, a ceremony or any other block may sit
 * inside a box or between boxes, wherever the organiser drags it; it takes its
 * time on the clock and holds no bouts. A group never goes inside another. A
 * class in no group runs as a block of its own, one class at a time — what a
 * running order did before groups existed.
 *
 * Shared by every bracketed sport: this is the day's shape, not a rule book.
 * Behind `events.session_modes`; with the flag off nothing here applies.
 */
final class RunSessions
{
    public const CLASS_AT_A_TIME = 'class';

    public const ROUND_BY_ROUND = 'round';

    public const MANUAL = 'manual';

    public const MODES = [self::CLASS_AT_A_TIME, self::ROUND_BY_ROUND, self::MANUAL];

    public static function enabled(): bool
    {
        return (bool) config('events.session_modes');
    }

    public static function normalise(?string $mode): string
    {
        return in_array($mode, self::MODES, true) ? $mode : self::CLASS_AT_A_TIME;
    }

    /**
     * One day's slots in the order the day actually runs: the main list by
     * position, and each group opened out in place — its header, then its
     * classes by their own position. A class whose group is gone (or is on
     * another day) stands on the main list.
     *
     * @param  Collection<int, EventRunSlot>  $slots  one day's slots, any order
     * @return Collection<int, EventRunSlot>
     */
    public static function ordered(Collection $slots): Collection
    {
        $sorted = $slots->sortBy(fn (EventRunSlot $s) => [$s->position, $s->id])->values();
        $groups = $sorted->where('kind', EventRunSlot::KIND_GROUP)->keyBy('id');
        $inGroup = fn (EventRunSlot $s) => $s->kind !== EventRunSlot::KIND_GROUP
            && $s->group_id !== null && $groups->has($s->group_id);

        $out = collect();
        foreach ($sorted as $slot) {
            if ($inGroup($slot)) {
                continue;
            }
            $out->push($slot);
            if ($slot->kind === EventRunSlot::KIND_GROUP) {
                foreach ($sorted as $member) {
                    if ($inGroup($member) && $member->group_id === $slot->id) {
                        $out->push($member);
                    }
                }
            }
        }

        return $out->values();
    }

    /**
     * Every division slot of the event, in running order, with the group it
     * belongs to and that group's method. A class in no group runs one class
     * at a time, as its own block.
     *
     * @return list<array{cat: int, parts: ?array, mode: string, session: string}>
     */
    public static function plan(ClubEvent $event): array
    {
        $byDay = EventRunSlot::where('event_id', $event->id)
            ->get(['id', 'uuid', 'category_id', 'parts', 'day', 'position', 'kind', 'mode', 'group_id'])
            ->groupBy('day')->sortKeys();

        $plan = [];
        foreach ($byDay as $day => $slots) {
            $groups = $slots->where('kind', EventRunSlot::KIND_GROUP)->keyBy('id');
            foreach (self::ordered($slots) as $slot) {
                if (! $slot->category_id) {
                    continue;
                }
                $group = $slot->group_id !== null ? $groups->get($slot->group_id) : null;
                $plan[] = [
                    'cat' => (int) $slot->category_id,
                    'parts' => $slot->parts,
                    'mode' => $group && self::enabled() ? self::normalise($group->mode) : self::CLASS_AT_A_TIME,
                    // Each ungrouped class is a block of its own.
                    'session' => $group ? $day.':g'.$group->id : $day.':s'.$slot->id,
                ];
            }
        }

        return $plan;
    }

    /**
     * Should the scheduler number this event by groups? Only when the flag is
     * on and the running order has a group — otherwise the old paths run
     * untouched, down to the bout number.
     */
    public static function inUse(ClubEvent $event): bool
    {
        return self::enabled()
            && EventRunSlot::where('event_id', $event->id)->where('kind', EventRunSlot::KIND_GROUP)->exists();
    }

    /** Set a group's method. Refuses a slot that is not one of this event's groups. */
    public static function set(ClubEvent $event, EventRunSlot $group, string $mode): bool
    {
        if ($group->event_id !== $event->id || $group->kind !== EventRunSlot::KIND_GROUP) {
            return false;
        }

        $group->forceFill(['mode' => self::normalise($mode)])->save();

        return true;
    }

    /**
     * The groups of one day, for a reader (the MCP tool), in running order.
     *
     * @return list<array{group: string, name: string, mode: string, divisions: list<string>}>
     */
    public static function describe(ClubEvent $event, int $day): array
    {
        $slots = self::ordered(EventRunSlot::where('event_id', $event->id)->where('day', $day)
            ->with('category:id,event_id,name')->get());

        $out = [];
        $index = [];
        foreach ($slots as $slot) {
            if ($slot->kind === EventRunSlot::KIND_GROUP) {
                $index[$slot->id] = count($out);
                $out[] = ['group' => $slot->uuid, 'name' => $slot->displayName(),
                    'mode' => self::normalise($slot->mode), 'divisions' => []];

                continue;
            }
            if ($slot->category && isset($index[$slot->group_id])) {
                $out[$index[$slot->group_id]]['divisions'][] = (string) $slot->category->name;
            }
        }

        return $out;
    }

    /**
     * Renumber the mats after a method changes — but never once the day has
     * been fought: the same rule every running-order save follows.
     */
    public static function renumberIfUnfought(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            $fought = \App\Events\Models\EventMatch::where('event_id', $event->id)
                ->where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
                ->whereNotNull('match_no')
                ->exists();

            if (! $fought) {
                app(\App\Sports\Combat\Engine\Scheduler::class)->scheduleAndNumber($event->fresh());
            }
        }, null, false);
    }
}
