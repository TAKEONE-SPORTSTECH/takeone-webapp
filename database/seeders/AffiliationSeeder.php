<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\ClubAffiliation;
use App\Models\SkillAcquisition;
use App\Models\AffiliationMedia;
use App\Models\TournamentEvent;
use Carbon\Carbon;

class AffiliationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user or create a sample one
        $user = User::first();
        if (!$user) {
            return; // No users to seed affiliations for
        }

        // Create additional clubs if they don't exist
        $clubs = [
            [
                'club_name' => 'Elite Boxing Club',
                'slug' => 'elite-boxing-club',
                'gps_lat' => 25.276987,
                'gps_long' => 55.296249,
            ],
            [
                'club_name' => 'Zen Martial Arts Academy',
                'slug' => 'zen-martial-arts-academy',
                'gps_lat' => 25.286987,
                'gps_long' => 55.306249,
            ],
            [
                'club_name' => 'Power Fitness Gym',
                'slug' => 'power-fitness-gym',
                'gps_lat' => 25.266987,
                'gps_long' => 55.286249,
            ],
        ];

        $createdClubs = [];
        foreach ($clubs as $clubData) {
            $club = Tenant::firstOrCreate(
                ['slug' => $clubData['slug']],
                array_merge($clubData, ['owner_user_id' => $user->id])
            );
            $createdClubs[] = $club;
        }

        // Sample club affiliations
        $affiliations = [
            [
                'club_name' => 'Elite Boxing Club',
                'start_date' => Carbon::parse('2020-01-15'),
                'end_date' => Carbon::parse('2021-12-31'),
                'location' => 'Downtown Fitness Center',
                'coaches' => ['Coach Mike Johnson', 'Coach Sarah Davis'],
                'description' => 'Premier boxing training facility focusing on technique and conditioning.',
                'logo' => 'https://via.placeholder.com/100x100/FF6B6B/FFFFFF?text=EBC',
                'skills' => [
                    ['skill_name' => 'Boxing', 'icon' => 'fas fa-fist-raised', 'duration_months' => 18, 'proficiency_level' => 'advanced'],
                    ['skill_name' => 'Fitness Training', 'icon' => 'fas fa-dumbbell', 'duration_months' => 12, 'proficiency_level' => 'intermediate'],
                    ['skill_name' => 'Footwork', 'icon' => 'fas fa-shoe-prints', 'duration_months' => 15, 'proficiency_level' => 'advanced'],
                ],
                'media' => [
                    ['media_type' => 'certificate', 'title' => 'Boxing Certification', 'media_url' => 'https://via.placeholder.com/300x200/4ECDC4/FFFFFF?text=Boxing+Cert', 'description' => 'Advanced Boxing Certificate'],
                    ['media_type' => 'photo', 'title' => 'Championship Photo', 'media_url' => 'https://via.placeholder.com/300x200/45B7D1/FFFFFF?text=Championship', 'description' => 'Regional Championship 2021'],
                ]
            ],
            [
                'club_name' => 'Zen Martial Arts Academy',
                'start_date' => Carbon::parse('2018-03-01'),
                'end_date' => Carbon::parse('2020-01-10'),
                'location' => 'East Side Dojo',
                'coaches' => ['Master Chen Wei', 'Instructor Lisa Park'],
                'description' => 'Traditional martial arts academy specializing in multiple disciplines.',
                'logo' => 'https://via.placeholder.com/100x100/96CEB4/FFFFFF?text=ZMA',
                'skills' => [
                    ['skill_name' => 'Taekwondo', 'icon' => 'fas fa-hand-rock', 'duration_months' => 20, 'proficiency_level' => 'expert'],
                    ['skill_name' => 'Karate', 'icon' => 'fas fa-fist-raised', 'duration_months' => 16, 'proficiency_level' => 'advanced'],
                    ['skill_name' => 'Self Defense', 'icon' => 'fas fa-shield-alt', 'duration_months' => 18, 'proficiency_level' => 'advanced'],
                ],
                'media' => [
                    ['media_type' => 'certificate', 'title' => 'Black Belt Certificate', 'media_url' => 'https://via.placeholder.com/300x200/FECA57/FFFFFF?text=Black+Belt', 'description' => 'Taekwondo Black Belt Certification'],
                ]
            ],
            [
                'club_name' => 'Power Fitness Gym',
                'start_date' => Carbon::parse('2022-06-01'),
                'end_date' => null, // Current affiliation
                'location' => 'West End Sports Complex',
                'coaches' => ['Trainer Alex Rodriguez', 'Trainer Emma Wilson'],
                'description' => 'Modern fitness center with comprehensive training programs.',
                'logo' => 'https://via.placeholder.com/100x100/FFEAA7/000000?text=PFG',
                'skills' => [
                    ['skill_name' => 'Weight Training', 'icon' => 'fas fa-dumbbell', 'duration_months' => 8, 'proficiency_level' => 'intermediate'],
                    ['skill_name' => 'Cardio Fitness', 'icon' => 'fas fa-heartbeat', 'duration_months' => 6, 'proficiency_level' => 'beginner'],
                    ['skill_name' => 'Nutrition', 'icon' => 'fas fa-apple-alt', 'duration_months' => 4, 'proficiency_level' => 'beginner'],
                ],
                'media' => [
                    ['media_type' => 'photo', 'title' => 'Gym Progress Photo', 'media_url' => 'https://via.placeholder.com/300x200/DD5E89/FFFFFF?text=Progress', 'description' => 'Before and after transformation'],
                ]
            ],
        ];

        $createdAffiliations = [];
        foreach ($affiliations as $affiliationData) {
            $skills = $affiliationData['skills'];
            $media = $affiliationData['media'];
            unset($affiliationData['skills'], $affiliationData['media']);

            $affiliationData['member_id'] = $user->id;

            $affiliation = ClubAffiliation::create($affiliationData);
            $createdAffiliations[] = $affiliation;

            // Create skills
            foreach ($skills as $skillData) {
                $affiliation->skillAcquisitions()->create($skillData);
            }

            // Create media
            foreach ($media as $mediaData) {
                $affiliation->affiliationMedia()->create($mediaData);
            }
        }

        // Link some tournament events to affiliations
        $tournamentEvents = TournamentEvent::where('user_id', $user->id)->get();

        if ($tournamentEvents->count() > 0 && count($createdAffiliations) > 0) {
            // Link first tournament to Elite Boxing Club affiliation
            $boxingAffiliation = collect($createdAffiliations)->firstWhere('club_name', 'Elite Boxing Club');
            if ($boxingAffiliation) {
                $boxingEvents = $tournamentEvents->where('sport', 'Boxing');
                foreach ($boxingEvents as $event) {
                    $event->update(['club_affiliation_id' => $boxingAffiliation->id]);
                }
            }

            // Link martial arts events to Zen Martial Arts Academy
            $martialArtsAffiliation = collect($createdAffiliations)->firstWhere('club_name', 'Zen Martial Arts Academy');
            if ($martialArtsAffiliation) {
                $martialArtsEvents = $tournamentEvents->whereIn('sport', ['Taekwondo', 'Karate', 'Martial Arts']);
                foreach ($martialArtsEvents as $event) {
                    $event->update(['club_affiliation_id' => $martialArtsAffiliation->id]);
                }
            }
        }
    }
}
