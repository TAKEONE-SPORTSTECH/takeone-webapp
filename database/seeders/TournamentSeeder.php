<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\TournamentEvent;
use App\Models\PerformanceResult;
use App\Models\NotesMedia;

class TournamentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user or create one
        $user = User::first() ?? User::factory()->create();

        // Create tournament events
        $events = [
            [
                'title' => 'National Swimming Championship 2023',
                'type' => 'championship',
                'sport' => 'Swimming',
                'date' => '2023-08-15',
                'time' => '10:00',
                'location' => 'Olympic Pool, Bahrain',
                'participants_count' => 50,
            ],
            [
                'title' => 'Regional Basketball Tournament',
                'type' => 'tournament',
                'sport' => 'Basketball',
                'date' => '2023-06-20',
                'time' => '14:00',
                'location' => 'Sports Arena, Manama',
                'participants_count' => 32,
            ],
            [
                'title' => 'Youth Football League Finals',
                'type' => 'championship',
                'sport' => 'Football',
                'date' => '2023-05-10',
                'time' => '16:00',
                'location' => 'National Stadium',
                'participants_count' => 16,
            ],
            [
                'title' => 'Tennis Open Championship',
                'type' => 'championship',
                'sport' => 'Tennis',
                'date' => '2023-04-05',
                'time' => '09:00',
                'location' => 'Tennis Club, Bahrain',
                'participants_count' => 24,
            ],
        ];

        foreach ($events as $eventData) {
            $event = TournamentEvent::create(array_merge($eventData, ['user_id' => $user->id]));

            // Add performance results
            if ($event->sport === 'Swimming') {
                PerformanceResult::create([
                    'tournament_event_id' => $event->id,
                    'description' => '100m Freestyle - Personal Best',
                    'medal_type' => '1st',
                    'points' => 100,
                ]);
            } elseif ($event->sport === 'Basketball') {
                PerformanceResult::create([
                    'tournament_event_id' => $event->id,
                    'description' => 'MVP Award',
                    'medal_type' => 'special',
                    'points' => 50,
                ]);
            } elseif ($event->sport === 'Football') {
                PerformanceResult::create([
                    'tournament_event_id' => $event->id,
                    'description' => 'Golden Boot Winner',
                    'medal_type' => 'special',
                    'points' => 75,
                ]);
                PerformanceResult::create([
                    'tournament_event_id' => $event->id,
                    'description' => 'Championship Title',
                    'medal_type' => '1st',
                    'points' => 200,
                ]);
            } elseif ($event->sport === 'Tennis') {
                PerformanceResult::create([
                    'tournament_event_id' => $event->id,
                    'description' => 'Singles Final',
                    'medal_type' => '2nd',
                    'points' => 80,
                ]);
            }

            // Add notes/media
            NotesMedia::create([
                'tournament_event_id' => $event->id,
                'note_text' => 'Outstanding performance in the championship. Set new personal records.',
                'media_link' => 'https://example.com/ceremony-photo.jpg',
            ]);
        }
    }
}
