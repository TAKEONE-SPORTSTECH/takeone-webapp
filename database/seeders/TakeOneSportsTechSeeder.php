<?php

namespace Database\Seeders;

use App\Models\ActivityCatalog;
use App\Models\ClubActivity;
use App\Models\ClubPackage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClubCreationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the flagship club "TAKEONE SportsTech", owned by the super-admin, with a
 * realistic set of club activities (named to match the GLOBAL activity catalog so
 * they link back to it) and a realistic package line-up wired to those activities.
 *
 * Idempotent: re-running only fills gaps (firstOrCreate by slug/name), never
 * duplicates. Depends on SuperAdminSeeder (owner) and ActivityCatalogSeeder
 * (the global activities the packages reference) having run first.
 */
class TakeOneSportsTechSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->first();
        if (! $owner) {
            $this->command?->error('No super-admin user found. Run SuperAdminSeeder first.');
            return;
        }

        // ── The club ──────────────────────────────────────────────────────────
        $club = Tenant::withTrashed()->where('slug', 'takeone-sportstech')->first();
        if (! $club) {
            $club = app(ClubCreationService::class)->create([
                'owner_user_id'          => $owner->id,
                'club_name'              => 'TAKEONE SportsTech',
                'slug'                   => 'takeone-sportstech',
                'slogan'                 => 'Where athletes are made.',
                'description'            => 'The flagship TAKEONE academy — martial arts, racquet sports and team training under one roof, powered by TAKEONE SportsTech.',
                'email'                  => 'hello@takeone.bh',
                'phone'                  => ['code' => '+973', 'number' => '17000000'],
                'country'                => 'Bahrain',
                'currency'               => 'BHD',
                'timezone'               => 'Asia/Bahrain',
                'address'                => 'Seef District, Manama, Bahrain',
                'gps_lat'                => 26.2361,
                'gps_long'               => 50.5476,
                'status'                 => 'active',
                'public_profile_enabled' => true,
                'vat_percentage'         => 10,
                'registration_fee'       => 15,
            ]);
            $this->command?->info('Created club: TAKEONE SportsTech (takeone-sportstech).');
        } else {
            $this->command?->info('Club takeone-sportstech already exists — reusing.');
        }

        // ── Club activities (named to match the global catalog) ────────────────
        // day/time schedule is illustrative; each links to a catalog entry by name.
        $activityDefs = [
            'TaeKwonDo WT' => [[ 'Saturday', '16:00' ], [ 'Monday', '18:00' ], [ 'Wednesday', '18:00' ]],
            'Boxing'       => [[ 'Sunday', '19:00' ], [ 'Tuesday', '19:00' ], [ 'Thursday', '19:00' ]],
            'Jiu Jitsu'    => [[ 'Saturday', '17:30' ], [ 'Monday', '19:30' ], [ 'Wednesday', '19:30' ]],
            'Judo'         => [[ 'Sunday', '17:00' ], [ 'Tuesday', '17:00' ]],
            'Padel'        => [[ 'Friday', '09:00' ], [ 'Saturday', '10:00' ]],
            'Tennis'       => [[ 'Sunday', '16:00' ], [ 'Wednesday', '16:00' ]],
            'Basket Ball'  => [[ 'Monday', '17:00' ], [ 'Thursday', '17:00' ]],
        ];

        $activities = [];
        foreach ($activityDefs as $name => $slots) {
            $activity = ClubActivity::withoutGlobalScopes()
                ->where('tenant_id', $club->id)->where('name', $name)->first();

            if (! $activity) {
                $activity = ClubActivity::create([
                    'tenant_id'          => $club->id,
                    'name'               => $name,
                    'duration_minutes'   => 60,
                    'frequency_per_week' => count($slots),
                    'schedule'           => array_map(fn ($s) => ['day' => $s[0], 'time' => $s[1]], $slots),
                    'description'        => $name.' training at TAKEONE SportsTech.',
                ]);
                // Fold it into the global directory (idempotent, keyed by slug).
                // These names already exist in the catalog, so this just links/enriches.
                try {
                    ActivityCatalog::contribute(
                        ['name' => $activity->name, 'description' => $activity->description],
                        $club->id
                    );
                } catch (\Throwable $e) {}
            }
            $activities[$name] = $activity;
        }

        // ── Packages (the enrollment offers) ───────────────────────────────────
        // [name, type, [activity names], age_min, age_max, gender, price, duration_months, session_count, description]
        $packageDefs = [
            ['TaeKwonDo — Juniors',   'single', ['TaeKwonDo WT'],                         5,  12, 'mixed', 35, 1, 12, 'Structured Taekwondo for kids: discipline, belts and fun. 12 sessions/month.'],
            ['TaeKwonDo — Adults',    'single', ['TaeKwonDo WT'],                        13, null, 'mixed', 45, 1, 12, 'World Taekwondo curriculum for teens and adults, sparring and forms.'],
            ['Boxing Fitness',        'single', ['Boxing'],                              16, null, 'mixed', 40, 1, 12, 'High-energy boxing conditioning — pads, bags and technique.'],
            ['Brazilian Jiu Jitsu',   'single', ['Jiu Jitsu'],                           14, null, 'mixed', 50, 1, 12, 'Gi & no-gi grappling from fundamentals to advanced rolls.'],
            ['Judo Foundations',      'single', ['Judo'],                                 8, null, 'mixed', 40, 1,  8, 'Throws, groundwork and competition Judo for all levels.'],
            ['Padel Membership',      'single', ['Padel'],                               12, null, 'mixed', 60, 1,  8, 'Coached padel sessions plus open-court access on weekends.'],
            ['Tennis Academy',        'single', ['Tennis'],                               6, null, 'mixed', 55, 1,  8, 'Progressive tennis coaching for juniors and adults.'],
            ['Basketball Squad',      'single', ['Basket Ball'],                         10,  18, 'mixed', 45, 1,  8, 'Team basketball development — skills, drills and scrimmage.'],
            ['All-Access Multi-Sport','multi',  ['TaeKwonDo WT','Boxing','Jiu Jitsu','Judo'], 13, null, 'mixed', 95, 1, 0, 'Train across every combat discipline — unlimited access to all mat classes.'],
        ];

        foreach ($packageDefs as [$name, $type, $actNames, $ageMin, $ageMax, $gender, $price, $months, $sessions, $desc]) {
            $pkg = ClubPackage::withoutGlobalScopes()
                ->where('tenant_id', $club->id)->where('name', $name)->first();

            if (! $pkg) {
                $pkg = ClubPackage::create([
                    'tenant_id'        => $club->id,
                    'name'             => $name,
                    'type'             => $type,
                    'age_min'          => $ageMin,
                    'age_max'          => $ageMax,
                    'gender'           => $gender,
                    'price'            => $price,
                    'registration_fee' => 0,
                    'duration_months'  => $months,
                    'session_count'    => $sessions,
                    'description'      => $desc,
                    'is_active'        => true,
                ]);
            }

            // Attach the activities (with their schedule on the pivot). syncWithoutDetaching = idempotent.
            $pivot = [];
            foreach ($actNames as $an) {
                if (isset($activities[$an])) {
                    $pivot[$activities[$an]->id] = ['schedule' => json_encode($activities[$an]->schedule)];
                }
            }
            if ($pivot) {
                $pkg->activities()->syncWithoutDetaching($pivot);
            }
        }

        $this->command?->info('Seeded '.count($activityDefs).' activities and '.count($packageDefs).' packages for TAKEONE SportsTech.');
    }
}
