<?php

namespace App\Http\Controllers;

use App\Models\ClubInstructor;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function show($instructorId)
    {
        $instructor = ClubInstructor::with([
            'user',
            'tenant',
            'reviews.reviewer',
        ])->findOrFail($instructorId);

        // Get activities this instructor teaches via club_package_activities
        $activities = \App\Models\ClubActivity::where('tenant_id', $instructor->tenant_id)
            ->whereHas('packages', function ($q) use ($instructor) {
                $q->where('club_package_activities.instructor_id', $instructor->id);
            })
            ->get();

        // Get schedule from package activities
        $scheduleItems = \DB::table('club_package_activities')
            ->where('instructor_id', $instructor->id)
            ->whereNotNull('schedule')
            ->pluck('schedule');

        $schedule = [];
        foreach ($scheduleItems as $item) {
            $decoded = is_string($item) ? json_decode($item, true) : $item;
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $day = $entry['day'] ?? null;
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

        // Build stats
        $stats = [
            'clients' => $instructor->tenant->memberships()->count() ?? 0,
            'sessions' => $activities->sum('frequency_per_week') * 4,
            'rating' => round($instructor->average_rating, 1),
            'certifications' => is_array($instructor->skills) ? count($instructor->skills) : 0,
        ];

        return view('trainer.show', compact('instructor', 'activities', 'schedule', 'stats'));
    }
}
