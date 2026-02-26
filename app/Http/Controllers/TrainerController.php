<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TrainerController extends Controller
{
    public function show(User $user)
    {
        return $this->renderProfile($user);
    }

    public function showPublic(User $user)
    {
        return $this->renderProfile($user);
    }

    private function renderProfile(User $user)
    {
        $user->load([
            'clubInstructors.tenant',
            'clubInstructors.reviews.reviewer',
        ]);

        $instructorIds = $user->clubInstructors->pluck('id');

        // Activities this person teaches across all their club positions
        $activities = \App\Models\ClubActivity::whereHas('packages', function ($q) use ($instructorIds) {
            $q->whereIn('club_package_activities.instructor_id', $instructorIds);
        })->get();

        // Build rich schedule slots (same structure as club public page)
        $packageActivities = \App\Models\ClubPackageActivity::with(['package.tenant', 'activity'])
            ->whereIn('instructor_id', $instructorIds)
            ->whereNotNull('schedule')
            ->get();

        $scheduleSlots = [];
        foreach ($packageActivities as $pa) {
            $scheduleData = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
            if (!is_array($scheduleData) || empty($scheduleData)) continue;

            $timeGroups = [];
            foreach ($scheduleData as $s) {
                $day   = strtolower($s['day'] ?? '');
                $start = $s['start_time'] ?? '';
                $end   = $s['end_time']   ?? '';
                if (!$day || !$start || !$end) continue;
                $key = $start . '-' . $end;
                if (!isset($timeGroups[$key])) {
                    $timeGroups[$key] = ['days' => [], 'start' => $start, 'end' => $end, 'facility_name' => $s['facility_name'] ?? null];
                }
                if (!in_array($day, $timeGroups[$key]['days'])) {
                    $timeGroups[$key]['days'][] = $day;
                }
            }

            foreach ($timeGroups as $slot) {
                $duration = abs(\Carbon\Carbon::parse($slot['end'])->diffInMinutes(\Carbon\Carbon::parse($slot['start'])));
                $scheduleSlots[] = [
                    'activity_name' => $pa->activity->title ?? $pa->activity->name ?? 'Class',
                    'picture_url'   => $pa->package->cover_image ?? null,
                    'package_name'  => $pa->package->name ?? null,
                    'club_name'     => $pa->package->tenant->club_name ?? null,
                    'club_slug'     => $pa->package->tenant->slug ?? null,
                    'days'          => $slot['days'],
                    'start'         => $slot['start'],
                    'end'           => $slot['end'],
                    'duration'      => $duration,
                    'facility_name' => $slot['facility_name'],
                ];
            }
        }
        usort($scheduleSlots, fn($a, $b) => strcmp($a['start'], $b['start']));
        $schedule = $scheduleSlots; // keep variable name for view

        // Aggregate reviews across all club instructor records
        $reviews = $user->clubInstructors->flatMap->reviews->sortByDesc('created_at');

        $stats = [
            'clients'          => $user->clubInstructors->sum(fn ($i) => optional($i->tenant)->memberships()->count() ?? 0),
            'sessions'         => $activities->sum('frequency_per_week') * 4,
            'rating'           => round($reviews->avg('rating') ?? 0, 1),
            'certifications'   => is_array($user->skills) ? count($user->skills) : 0,
        ];

        return view('trainer.show', compact('user', 'activities', 'scheduleSlots', 'stats', 'reviews'));
    }
}
