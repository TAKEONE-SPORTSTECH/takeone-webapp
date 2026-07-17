<?php

namespace App\Traits;

use App\Models\ClassAttendance;
use App\Models\User;
use App\Support\SyncedClassToken;
use Carbon\Carbon;

/**
 * The member-profile "Attendance" summary (ring % + completed/no-shows/total,
 * plus the per-session schedule list) is sourced from real enrollment data,
 * not the free-text attendance log: total sessions = how many times the
 * package's weekly class slots (the `schedule` on each club_package_activities
 * row) fall within the member's subscription period (start_date..end_date);
 * completed = class_attendances rows for those package activities (a row
 * only exists once a trainer marks that class attended). A class only counts
 * toward the total/rate once it has actually ENDED — a session that hasn't
 * started (or is still in progress) can't yet be marked absent, so it's
 * excluded from the percentage and shown in the list as "upcoming" instead.
 */
trait ComputesAttendanceStats
{
    private const WEEKDAYS = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6,
    ];

    /**
     * @return array{sessionsCompleted:int,noShows:int,totalSessions:int,attendanceRate:float,scheduleSessions:\Illuminate\Support\Collection}
     */
    protected function computeAttendanceStats(User $member): array
    {
        $subscriptions = $member->subscriptions()
            ->whereIn('status', ['active', 'pending'])
            ->with([
                'tenant:id,club_name,slug,translations',
                'package.packageActivities.activity:id,name',
                'package.packageActivities.instructor.user:id,name,full_name',
            ])
            ->get();

        $occurrences = collect();

        foreach ($subscriptions as $sub) {
            $start = $sub->start_date ? Carbon::parse($sub->start_date)->startOfDay() : null;
            $end = $sub->end_date ? Carbon::parse($sub->end_date)->startOfDay() : null;
            if (! $start || ! $end || $end->lt($start)) {
                continue;
            }

            $club = $sub->tenant?->club_name;

            foreach ($sub->package?->packageActivities ?? [] as $pa) {
                $schedule = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
                $coach = $pa->instructor?->user?->full_name ?? $pa->instructor?->user?->name;
                $title = $pa->activity?->name ?? $sub->package?->name;

                foreach ($schedule as $slot) {
                    $day = strtolower($slot['day'] ?? '');
                    $dow = self::WEEKDAYS[$day] ?? null;
                    $startTime = (string) ($slot['start_time'] ?? '');
                    if ($dow === null || $startTime === '') {
                        continue;
                    }

                    foreach ($this->datesForWeekday($start, $end, $dow) as $date) {
                        $occurrences->push((object) [
                            'package_activity_id' => $pa->id,
                            'day' => $day,
                            'start_time' => $startTime,
                            'end_time' => $slot['end_time'] ?? null,
                            'date' => $date,
                            'title' => $slot['title'] ?? $title,
                            'club' => $club,
                            'coach' => $slot['coach'] ?? $coach,
                        ]);
                    }
                }
            }
        }

        $packageActivityIds = $occurrences->pluck('package_activity_id')->unique();
        $attendedKeys = $packageActivityIds->isEmpty()
            ? collect()
            : ClassAttendance::where('user_id', $member->id)
                ->whereIn('package_activity_id', $packageActivityIds)
                ->get(['package_activity_id', 'slot_day', 'slot_start', 'date'])
                ->map(fn ($a) => "{$a->package_activity_id}|{$a->slot_day}|{$a->slot_start}|{$a->date->toDateString()}")
                ->flip();

        $now = Carbon::now();

        $scheduleSessions = $occurrences
            ->map(function ($o) use ($attendedKeys, $now) {
                $key = "{$o->package_activity_id}|{$o->day}|{$o->start_time}|{$o->date->toDateString()}";
                $o->attended = $attendedKeys->has($key);
                $o->token = SyncedClassToken::encode($o->package_activity_id, $o->day, $o->start_time);
                $o->url = route('me.schedule.synced', ['token' => $o->token, 'on' => $o->date->toDateString()]);

                // A class only becomes eligible to be marked absent once it has ENDED —
                // use end_time (fall back to start_time if the slot has none).
                $endClock = $o->end_time ?: $o->start_time;
                $endsAt = Carbon::parse($o->date->toDateString().' '.$endClock);
                $o->has_ended = $endsAt->lte($now);
                $o->status = $o->attended ? 'attended' : ($o->has_ended ? 'missed' : 'upcoming');

                return $o;
            })
            ->sortByDesc(fn ($o) => $o->date->toDateString().$o->start_time)
            ->values();

        // "Total sessions" = every class the subscription entitles them to across the whole
        // enrollment period (past + upcoming). The rate/no-shows only judge the ones that have
        // already ENDED — an upcoming class can't be marked absent yet, so it counts toward the
        // total but not toward completed/no-shows/the percentage.
        $totalSessions = $scheduleSessions->count();
        $elapsedCount = $scheduleSessions->where('has_ended', true)->count();
        $sessionsCompleted = $scheduleSessions->where('has_ended', true)->where('attended', true)->count();
        $noShows = max($elapsedCount - $sessionsCompleted, 0);
        $attendanceRate = $elapsedCount > 0 ? round(($sessionsCompleted / $elapsedCount) * 100, 1) : 0;

        return [
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
        ];
    }

    /** @return \Carbon\Carbon[] every date matching weekday $dow (0=Sunday..6=Saturday) within [$start, $end], inclusive. */
    private function datesForWeekday(Carbon $start, Carbon $end, int $dow): array
    {
        $diff = ($dow - $start->dayOfWeek + 7) % 7;
        $dates = [];
        for ($d = $start->copy()->addDays($diff); $d->lte($end); $d->addDays(7)) {
            $dates[] = $d->copy();
        }

        return $dates;
    }
}
