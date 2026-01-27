<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\ClubAffiliation;
use App\Models\SkillAcquisition;
use App\Models\AffiliationMedia;
use App\Models\ClubPackage;
use App\Models\ClubActivity;
use App\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackageActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AffiliationsDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample clubs/tenants
        $clubs = [
            ['name' => 'Elite Martial Arts Academy', 'location' => 'Manama, Bahrain'],
            ['name' => 'Champions Boxing Club', 'location' => 'Riffa, Bahrain'],
            ['name' => 'Fitness First Gym', 'location' => 'Seef, Bahrain'],
            ['name' => 'Warrior Taekwondo Center', 'location' => 'Muharraq, Bahrain'],
        ];

        // Skills should be the main sport/martial art, not subdivisions
        $martialArtsSkills = [
            'Taekwondo',
            'Boxing',
            'Karate',
            'Kickboxing',
            'Muay Thai',
            'Jiu-Jitsu',
            'Judo',
            'Wrestling',
            'MMA'
        ];

        $fitnessSkills = [
            'Strength Training',
            'Cardio Training',
            'CrossFit',
            'Functional Training',
            'Yoga',
            'Pilates',
            'Calisthenics'
        ];

        $instructorNames = [
            'Master Ahmed Al-Khalifa', 'Coach Sarah Johnson', 'Sensei Mohammed Ali',
            'Coach David Martinez', 'Master Fatima Hassan', 'Coach John Smith',
            'Instructor Lisa Chen', 'Coach Omar Abdullah', 'Master Kim Lee',
            'Coach Maria Garcia'
        ];

        // Get all users
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please create users first.');
            return;
        }

        $this->command->info('Starting to seed affiliations data for ' . $users->count() . ' users...');

        foreach ($users as $user) {
            // Track skills acquired by this user (each skill only once in lifetime)
            $userSkillsAcquired = [];

            // Each user gets 2-4 club affiliations
            $affiliationCount = rand(2, 4);

            $this->command->info("Creating {$affiliationCount} affiliations for user: {$user->full_name}");

            for ($i = 0; $i < $affiliationCount; $i++) {
                $clubData = $clubs[array_rand($clubs)];

                // Create dates - older affiliations first
                $yearsAgo = $affiliationCount - $i;
                $startDate = Carbon::now()->subYears($yearsAgo)->subMonths(rand(0, 11));

                // Some affiliations are ongoing (no end date)
                $isOngoing = ($i === 0 && rand(0, 1) === 1); // 50% chance first affiliation is ongoing
                $endDate = $isOngoing ? null : $startDate->copy()->addMonths(rand(6, 24));

                // Get or create tenant for this club
                $tenant = Tenant::firstOrCreate(
                    ['club_name' => $clubData['name']],
                    [
                        'owner_user_id' => 1, // Assuming admin user
                        'club_name' => $clubData['name'],
                        'slug' => \Illuminate\Support\Str::slug($clubData['name']),
                        'address' => $clubData['location'],
                        'country' => 'Bahrain',
                    ]
                );

                // Create club affiliation
                $affiliation = ClubAffiliation::create([
                    'member_id' => $user->id,
                    'club_name' => $clubData['name'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'location' => $clubData['location'],
                    'coaches' => array_rand(array_flip($instructorNames), rand(2, 3)),
                    'description' => 'Member of ' . $clubData['name'] . ' training in various disciplines.',
                ]);

                $this->command->info("  - Created affiliation: {$clubData['name']} ({$startDate->format('Y-m-d')} to " . ($endDate ? $endDate->format('Y-m-d') : 'Present') . ")");

                // Create instructors for this club
                $clubInstructors = [];
                foreach (array_rand(array_flip($instructorNames), rand(3, 5)) as $instructorName) {
                    // Create a user for the instructor if not exists
                    $instructorUser = User::firstOrCreate(
                        ['email' => strtolower(str_replace(' ', '.', $instructorName)) . '@club.com'],
                        [
                            'name' => $instructorName,
                            'full_name' => $instructorName,
                            'password' => bcrypt('password'),
                            'gender' => 'm',
                        ]
                    );

                    $instructor = ClubInstructor::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'user_id' => $instructorUser->id,
                        ],
                        [
                            'role' => ['Martial Arts', 'Fitness', 'Boxing'][rand(0, 2)] . ' Instructor',
                            'experience_years' => rand(5, 15),
                            'rating' => rand(40, 50) / 10, // 4.0 to 5.0
                            'skills' => json_encode(['Coaching', 'Training', 'Mentoring']),
                            'bio' => 'Experienced instructor with over 10 years of teaching experience.',
                        ]
                    );
                    $clubInstructors[] = $instructor;
                }

                // Create 2-3 packages for this affiliation
                $packageCount = rand(2, 3);
                for ($p = 0; $p < $packageCount; $p++) {
                    $packageStartDate = $startDate->copy()->addMonths($p * 6);
                    $packageEndDate = $packageStartDate->copy()->addMonths(rand(3, 6));

                    // Don't create packages beyond affiliation end date
                    if ($endDate && $packageStartDate->gt($endDate)) {
                        break;
                    }

                    $package = ClubPackage::create([
                        'tenant_id' => $tenant->id,
                        'name' => ['Beginner Package', 'Intermediate Package', 'Advanced Package', 'Elite Training'][rand(0, 3)],
                        'type' => ['single', 'multi'][rand(0, 1)],
                        'age_min' => $user->age - 5,
                        'age_max' => $user->age + 5,
                        'gender' => 'mixed',
                        'price' => rand(30, 150),
                        'duration_months' => rand(1, 6),
                        'session_count' => rand(12, 48),
                        'description' => 'Comprehensive training package',
                        'is_active' => true,
                    ]);

                    // Create 2-4 activities for this package
                    $activityCount = rand(2, 4);
                    $packageActivities = [];

                    for ($a = 0; $a < $activityCount; $a++) {
                        $activity = ClubActivity::create([
                            'tenant_id' => $tenant->id,
                            'name' => ['Martial Arts', 'Boxing', 'Fitness'][rand(0, 2)] . ' Class ' . chr(65 + $a),
                            'duration_minutes' => [45, 60, 90][rand(0, 2)],
                            'frequency_per_week' => rand(2, 4),
                            'schedule' => [
                                ['day' => 'Monday', 'time' => '16:00'],
                                ['day' => 'Wednesday', 'time' => '16:00'],
                                ['day' => 'Saturday', 'time' => '10:00'],
                            ],
                            'description' => 'Regular training sessions',
                        ]);

                        // Link activity to package with instructor
                        $instructor = $clubInstructors[array_rand($clubInstructors)];
                        ClubPackageActivity::create([
                            'package_id' => $package->id,
                            'activity_id' => $activity->id,
                            'instructor_id' => $instructor->id,
                        ]);

                        $packageActivities[] = [
                            'activity' => $activity,
                            'instructor' => $instructor,
                        ];
                    }

                    // Create subscription for this package
                    // Make sure tenant exists before creating subscription
                    if ($tenant && $tenant->id) {
                        $subscription = ClubMemberSubscription::create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $user->id,
                            'club_affiliation_id' => $affiliation->id,
                            'package_id' => $package->id,
                            'start_date' => $packageStartDate,
                            'end_date' => $packageEndDate,
                            'status' => $packageEndDate->lt(Carbon::now()) ? 'expired' : 'active',
                            'payment_status' => 'paid',
                            'amount_paid' => $package->price,
                            'amount_due' => 0,
                        ]);
                    }

                    // Create skills from this package's activities
                    foreach ($packageActivities as $pa) {
                        $activity = $pa['activity'];
                        $instructor = $pa['instructor'];

                        // Determine skill category based on activity name
                        $skillPool = str_contains($activity->name, 'Fitness') ? $fitnessSkills : $martialArtsSkills;

                        // Each activity teaches 1-3 skills
                        $skillsToTeach = array_rand(array_flip($skillPool), rand(1, 3));
                        if (!is_array($skillsToTeach)) {
                            $skillsToTeach = [$skillsToTeach];
                        }

                        foreach ($skillsToTeach as $skillName) {
                            // IMPORTANT: Each skill can only be acquired ONCE in a person's lifetime
                            // Skip if user already has this skill
                            if (in_array($skillName, $userSkillsAcquired)) {
                                continue;
                            }

                            // Mark this skill as acquired
                            $userSkillsAcquired[] = $skillName;

                            $skillStartDate = $packageStartDate->copy()->addDays(rand(0, 30));

                            // Skill is acquired once and stays forever (no end date)
                            // The person has this skill for life once they learn it
                            $skillEndDate = null;

                            // Calculate duration from start to now
                            $durationMonths = $skillStartDate->diffInMonths(Carbon::now());
                            $durationMonths = max(1, $durationMonths);

                            // Calculate proficiency level based on duration
                            $proficiencyIndex = min(3, max(0, floor($durationMonths / 6)));
                            $proficiencyLevel = ['beginner', 'intermediate', 'advanced', 'expert'][$proficiencyIndex];

                            SkillAcquisition::create([
                                'club_affiliation_id' => $affiliation->id,
                                'package_id' => $package->id,
                                'activity_id' => $activity->id,
                                'instructor_id' => $instructor->id,
                                'skill_name' => $skillName,
                                'icon' => 'bi-star',
                                'duration_months' => $durationMonths,
                                'start_date' => $skillStartDate,
                                'end_date' => $skillEndDate,
                                'proficiency_level' => $proficiencyLevel,
                                'notes' => 'Skill acquired at ' . $clubData['name'],
                            ]);
                        }
                    }
                }

                // Add some affiliation media (certificates, photos)
                $mediaCount = rand(1, 3);
                for ($m = 0; $m < $mediaCount; $m++) {
                    AffiliationMedia::create([
                        'club_affiliation_id' => $affiliation->id,
                        'media_type' => ['certificate', 'photo', 'document'][rand(0, 2)],
                        'title' => ['Membership Certificate', 'Training Photo', 'Achievement Award'][rand(0, 2)],
                        'media_url' => 'affiliations/sample_' . rand(1, 100) . '.jpg',
                        'description' => 'Sample media file',
                    ]);
                }
            }

            // No cross-club skill progression needed
            // Each skill is acquired once and belongs to the person for life
        }

        $this->command->info('✓ Affiliations data seeded successfully!');
    }
}
