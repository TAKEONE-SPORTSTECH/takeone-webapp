<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Schedule from all club package activities
        $scheduleItems = DB::table('club_package_activities')
            ->whereIn('instructor_id', $instructorIds)
            ->whereNotNull('schedule')
            ->pluck('schedule');

        $schedule = [];
        foreach ($scheduleItems as $item) {
            $decoded = is_string($item) ? json_decode($item, true) : $item;
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $day  = $entry['day'] ?? null;
                    $time = $entry['time'] ?? $entry['start_time'] ?? null;
                    if ($day && $time) {
                        if (!isset($schedule[$day])) {
                            $schedule[$day] = [];
                        }
                        $schedule[$day][] = $time;
                    }
                }
            }
        }

        // Aggregate reviews across all club instructor records
        $reviews = $user->clubInstructors->flatMap->reviews->sortByDesc('created_at');

        $stats = [
            'clients'          => $user->clubInstructors->sum(fn ($i) => optional($i->tenant)->memberships()->count() ?? 0),
            'sessions'         => $activities->sum('frequency_per_week') * 4,
            'rating'           => round($reviews->avg('rating') ?? 0, 1),
            'certifications'   => is_array($user->skills) ? count($user->skills) : 0,
        ];

        return view('trainer.show', compact('user', 'activities', 'schedule', 'stats', 'reviews'));
    }
}
