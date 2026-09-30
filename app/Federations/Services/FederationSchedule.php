<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationScheduleDay;
use App\Federations\Models\FederationScheduleSlot;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Schedule tab: an event's days and the sessions on each.
 *
 * Any staff member who can open the event may edit it — the same scope as
 * the draw (FederationDraws): the viewer's subtree and, for sport-limited
 * staff, their sports. A day or session is always found THROUGH an event in
 * that scope, so a uuid from another federation answers exactly like one that
 * does not exist.
 */
class FederationSchedule
{
    public const OPS = ['day.add', 'day.update', 'day.move', 'day.delete', 'slot.add', 'slot.update', 'slot.delete'];

    public const MAX_DAYS = 31;

    public const MAX_SLOTS_PER_DAY = 80;

    public function __construct(private FederationDirectory $directory) {}

    private function eventsQuery(array $access)
    {
        $q = FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q;
    }

    /** `__TAKEONE_FED.schedules`: event uuid => [{id, title, slots: [{id, time, end, what, where}]}]. */
    public function payload(array $access): object
    {
        $events = $this->eventsQuery($access)->pluck('uuid', 'id');
        $out = [];
        FederationScheduleDay::whereIn('federation_event_id', $events->keys())
            ->with('slots')->orderBy('position')->orderBy('id')->get()
            ->each(function (FederationScheduleDay $d) use ($events, &$out) {
                $out[$events[$d->federation_event_id]][] = $this->shapeDay($d);
            });

        return (object) $out;
    }

    /** One event's days, as the payload holds them. */
    private function eventDays(FederationEvent $event): array
    {
        return FederationScheduleDay::where('federation_event_id', $event->id)
            ->with('slots')->orderBy('position')->orderBy('id')->get()
            ->map(fn (FederationScheduleDay $d) => $this->shapeDay($d))->all();
    }

    private function shapeDay(FederationScheduleDay $d): array
    {
        return [
            'id' => $d->uuid,
            'title' => (string) $d->title,
            'slots' => $d->slots->map(fn (FederationScheduleSlot $s) => [
                'id' => $s->uuid,
                'time' => $s->starts_at,
                'end' => $s->ends_at,
                'what' => $s->title,
                'where' => (string) $s->location,
            ])->values()->all(),
        ];
    }

    /**
     * Apply one change.
     *
     * @return array{ok: bool, event: ?string, days: array, message?: string}
     */
    public function apply(User $user, array $access, string $op, array $data): array
    {
        $fail = fn (string $msg = 'That schedule item is no longer there.') => ['ok' => false, 'event' => null, 'days' => [], 'message' => $msg];

        return DB::transaction(function () use ($user, $access, $op, $data, $fail) {
            [$event, $day, $slot] = [null, null, null];

            if ($op === 'day.add') {
                $event = $this->eventsQuery($access)->where('uuid', $data['event'] ?? '')->first();
            } elseif (str_starts_with($op, 'day.') || $op === 'slot.add') {
                $day = $this->dayInScope($access, $data['day'] ?? '');
                $event = $day?->event;
            } else {
                $slot = $this->slotInScope($access, $data['slot'] ?? '');
                $day = $slot?->day;
                $event = $day?->event;
            }
            if (! $event) {
                return $fail();
            }

            switch ($op) {
                case 'day.add':
                    $count = FederationScheduleDay::where('federation_event_id', $event->id)->count();
                    if ($count >= self::MAX_DAYS) {
                        return $fail('An event can have at most '.self::MAX_DAYS.' days.');
                    }
                    FederationScheduleDay::create([
                        'federation_event_id' => $event->id,
                        'position' => (int) FederationScheduleDay::where('federation_event_id', $event->id)->max('position') + 1,
                        'title' => $data['title'] ?? null,
                        'updated_by' => $user->id,
                    ]);
                    break;

                case 'day.update':
                    $day->update(['title' => $data['title'] ?? null, 'updated_by' => $user->id]);
                    break;

                case 'day.move':
                    $days = FederationScheduleDay::where('federation_event_id', $event->id)
                        ->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
                    $i = $days->search(fn ($d) => $d->id === $day->id);
                    $j = ($data['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
                    if ($i !== false && $j >= 0 && $j < $days->count()) {
                        $list = $days->all();
                        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
                        foreach ($list as $k => $d) {
                            if ($d->position !== $k + 1) {
                                $d->update(['position' => $k + 1]);
                            }
                        }
                    }
                    break;

                case 'day.delete':
                    // No files hang off a schedule: rows only.
                    FederationScheduleSlot::where('federation_schedule_day_id', $day->id)->delete();
                    $day->delete();
                    break;

                case 'slot.add':
                    if (FederationScheduleSlot::where('federation_schedule_day_id', $day->id)->count() >= self::MAX_SLOTS_PER_DAY) {
                        return $fail('A day can have at most '.self::MAX_SLOTS_PER_DAY.' sessions.');
                    }
                    FederationScheduleSlot::create($this->slotFields($data) + [
                        'federation_schedule_day_id' => $day->id,
                        'updated_by' => $user->id,
                    ]);
                    break;

                case 'slot.update':
                    $slot->update($this->slotFields($data) + ['updated_by' => $user->id]);
                    break;

                case 'slot.delete':
                    $slot->delete();
                    break;
            }

            return ['ok' => true, 'event' => $event->uuid, 'days' => $this->eventDays($event)];
        });
    }

    private function slotFields(array $data): array
    {
        return [
            'starts_at' => $data['time'],
            'ends_at' => $data['end'] ?? null,
            'title' => $data['what'],
            'location' => $data['where'] ?? null,
        ];
    }

    private function dayInScope(array $access, string $uuid): ?FederationScheduleDay
    {
        return FederationScheduleDay::where('uuid', $uuid)
            ->whereIn('federation_event_id', $this->eventsQuery($access)->select('id'))
            ->lockForUpdate()->first();
    }

    private function slotInScope(array $access, string $uuid): ?FederationScheduleSlot
    {
        return FederationScheduleSlot::where('uuid', $uuid)
            ->whereIn('federation_schedule_day_id', FederationScheduleDay::whereIn('federation_event_id', $this->eventsQuery($access)->select('id'))->select('id'))
            ->lockForUpdate()->first();
    }
}
