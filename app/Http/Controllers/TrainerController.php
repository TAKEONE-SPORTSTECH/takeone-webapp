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
            try {
                $scheduleData = is_string($pa->schedule) ? json_decode($pa->schedule, true, 512, JSON_THROW_ON_ERROR) : $pa->schedule;
            } catch (\JsonException) {
                continue;
            }
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
                    'club_country'  => $pa->package->tenant->country_code ?? 'bh',
                    'club_url'      => $pa->package->tenant?->url ?? null,
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

        // Emoji reactions on classes this person actually taught — their own
        // classes (minus dates substituted away) plus sessions they covered.
        $myPaIds  = \App\Models\ClubPackageActivity::whereIn('instructor_id', $instructorIds)->pluck('id');
        $subRows  = \App\Models\ClassSubstitution::where('substitute_user_id', $user->id)->get();
        $coveredKeys = $subRows->map(fn ($r) => $r->package_activity_id . '|' . $r->slot_day . '|' . (string) $r->slot_start . '|' . $r->date->toDateString())->flip();
        $awayKeys = \App\Models\ClassSubstitution::whereIn('package_activity_id', $myPaIds)
            ->where('substitute_user_id', '!=', $user->id)->get()
            ->map(fn ($r) => $r->package_activity_id . '|' . $r->slot_day . '|' . (string) $r->slot_start . '|' . $r->date->toDateString())->flip();

        $reactionRows = \App\Models\ClassReaction::whereIn('package_activity_id', $myPaIds->merge($subRows->pluck('package_activity_id'))->unique())
            ->get()
            ->filter(function ($r) use ($myPaIds, $coveredKeys, $awayKeys) {
                $key = $r->package_activity_id . '|' . $r->slot_day . '|' . (string) $r->slot_start . '|' . $r->date->toDateString();
                if (isset($coveredKeys[$key])) return true;                                  // they covered this session
                return $myPaIds->contains($r->package_activity_id) && ! isset($awayKeys[$key]); // their class, not handed off
            });
        $reactions     = $reactionRows->groupBy('emoji')->map->count()->sortDesc();
        $reactionTotal = $reactionRows->count();

        $stats = [
            'clients'          => $user->clubInstructors->sum(fn ($i) => optional($i->tenant)->memberships()->count() ?? 0),
            'sessions'         => $activities->sum('frequency_per_week') * 4,
            'rating'           => round($reviews->avg('rating') ?? 0, 1),
            'certifications'   => is_array($user->skills) ? count($user->skills) : 0,
        ];

        $isMobile = request()->attributes->get('is_mobile', false);
        $view = $isMobile && view()->exists('trainer.mobile.show') ? 'trainer.mobile.show' : 'trainer.show';

        return view($view, compact('user', 'activities', 'scheduleSlots', 'stats', 'reviews', 'reactions', 'reactionTotal'));
    }
}
