<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ClubActivity;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\ClubFacility;
use App\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubPackageActivity;
use App\Models\ClubProduct;
use App\Models\ClubTransaction;
use App\Models\ClubProductCategory;
use App\Models\ClubTimelinePost;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\Duel;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPost;
use App\Models\UserPostComment;
use App\Models\UserPostLike;
use App\Models\UserScheduleSession;
use App\Models\UserStory;
use App\Support\DemoManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a large, cohesive DEMO dataset wired to the super-admin account so every
 * surface (explore, club admin panels, business switcher, /me feed, schedule,
 * challenges, events, market) looks fully populated.
 *
 * EVERYTHING it creates is recorded to a manifest (storage/app/demo/manifest.json)
 * AND tagged (demo clubs slug `demo-*`, demo users `@demo.takeone.bh`) so it can be
 * removed cleanly with `php artisan demo:purge` before going live. It never touches
 * real imported members or the super-admin user itself.
 */
class DemoSeed extends Command
{
    protected $signature = 'demo:seed
        {--clubs=6 : Number of demo clubs to create}
        {--members=40 : Members per club}
        {--admin=superadmin@takeone.bh : Super-admin email to wire the demo to}
        {--fresh : Purge any existing demo data first}';

    protected $description = 'Seed a large demo dataset (clubs, trainers, members, feeds, schedule, challenges, events, market) wired to the super admin, fully removable via demo:purge.';

    private DemoManifest $m;
    private User $admin;
    private array $roleIds = [];

    /** Sport blueprints: name, slug, slogan, icon, color, activity templates, package templates. */
    private function blueprints(): array
    {
        return [
            [
                'sport' => 'Taekwondo', 'name' => 'Tiger Taekwondo Academy', 'slug' => 'tiger-taekwondo',
                'slogan' => 'Discipline. Power. Respect.', 'color' => '#7c3aed', 'icon' => 'bi-award',
                'activities' => ['Little Tigers (4-7)', 'Juniors Sparring', 'Adults Poomsae', 'Black Belt Club'],
                'packages' => [
                    ['Kids Foundation', 30, 4, 9, 'mixed'], ['Juniors Competition', 45, 9, 15, 'mixed'],
                    ['Adults All-Access', 55, 16, 60, 'mixed'], ['Elite Black Belt', 80, 14, 60, 'mixed'],
                ],
                'role' => 'Taekwondo Master',
            ],
            [
                'sport' => 'Boxing', 'name' => 'Knockout Boxing Club', 'slug' => 'knockout-boxing',
                'slogan' => 'Float. Sting. Win.', 'color' => '#ef4444', 'icon' => 'bi-bullseye',
                'activities' => ['Beginner Boxing', 'Pad Work & Bag', 'Sparring Squad', 'White-Collar Fitness'],
                'packages' => [
                    ['Fundamentals', 35, 14, 60, 'mixed'], ['Fighter Track', 60, 16, 45, 'mixed'],
                    ['Ladies Boxfit', 40, 16, 55, 'female'], ['Unlimited Pro', 75, 16, 60, 'mixed'],
                ],
                'role' => 'Head Coach',
            ],
            [
                'sport' => 'Fitness', 'name' => 'Pulse Fitness Gym', 'slug' => 'pulse-fitness',
                'slogan' => 'Stronger every day.', 'color' => '#10b981', 'icon' => 'bi-heart-pulse',
                'activities' => ['Strength & Conditioning', 'HIIT Burn', 'Mobility & Recovery', 'Powerlifting'],
                'packages' => [
                    ['Monthly Access', 25, 16, 70, 'mixed'], ['PT 8-Pack', 90, 16, 70, 'mixed'],
                    ['Ladies Only', 30, 16, 70, 'female'], ['Annual Saver', 220, 16, 70, 'mixed'],
                ],
                'role' => 'Strength Coach',
            ],
            [
                'sport' => 'Swimming', 'name' => 'AquaForce Swim Center', 'slug' => 'aquaforce-swim',
                'slogan' => 'Find your flow.', 'color' => '#0ea5e9', 'icon' => 'bi-water',
                'activities' => ['Learn to Swim (Kids)', 'Stroke Technique', 'Squad Training', 'Aqua Aerobics'],
                'packages' => [
                    ['Kids Splash', 40, 4, 12, 'mixed'], ['Improvers', 45, 8, 16, 'mixed'],
                    ['Competitive Squad', 70, 10, 22, 'mixed'], ['Adult Aqua', 35, 16, 70, 'mixed'],
                ],
                'role' => 'Swim Coach',
            ],
            [
                'sport' => 'Padel', 'name' => 'Smash Padel Club', 'slug' => 'smash-padel',
                'slogan' => 'Game on.', 'color' => '#f59e0b', 'icon' => 'bi-dribbble',
                'activities' => ['Beginner Clinic', 'Intermediate Drills', 'Match Play Nights', 'Junior Academy'],
                'packages' => [
                    ['Clinic Pass', 30, 12, 60, 'mixed'], ['Drill & Play', 50, 14, 60, 'mixed'],
                    ['Juniors', 35, 8, 16, 'mixed'], ['Court Membership', 65, 16, 70, 'mixed'],
                ],
                'role' => 'Padel Pro',
            ],
            [
                'sport' => 'Yoga', 'name' => 'Zen Flow Yoga Studio', 'slug' => 'zen-flow-yoga',
                'slogan' => 'Breathe. Move. Be.', 'color' => '#ec4899', 'icon' => 'bi-flower1',
                'activities' => ['Sunrise Vinyasa', 'Hatha Basics', 'Power Yoga', 'Restorative & Yin'],
                'packages' => [
                    ['Drop-in Pass', 20, 16, 70, 'mixed'], ['Monthly Unlimited', 45, 16, 70, 'mixed'],
                    ['Prenatal', 35, 18, 45, 'female'], ['Annual Zen', 380, 16, 70, 'mixed'],
                ],
                'role' => 'Lead Instructor',
            ],
        ];
    }

    private function firstNames(): array
    {
        return ['Ahmed','Fatima','Yusuf','Layla','Omar','Noor','Sara','Khalid','Mariam','Ali','Hessa','Hamad',
            'Aisha','Rashid','Maya','Salem','Dana','Tariq','Reem','Faisal','Huda','Nasser','Lina','Saeed',
            'Jana','Bader','Amal','Zaid','Sana','Marwan','Liam','Emma','Noah','Olivia','Ethan','Sophia',
            'Lucas','Mia','Adam','Zara','Karim','Leen','Hadi','Ghada','Sami','Rana','Yara','Majid'];
    }

    private function lastNames(): array
    {
        return ['Al Khalifa','Al Dosari','Hassan','Al Maktoum','Saleh','Al Sayed','Buallay','Al Naimi','Karimi',
            'Al Mansoori','Haddad','Al Hashimi','Nasser','Al Rumaihi','Mahmood','Al Kuwari','Sharif','Al Balooshi',
            'Yousif','Al Thani','Rashed','Al Marzooqi','Fakhro','Al Zayani','Smith','Garcia','Khan','Martinez'];
    }

    public function handle(): int
    {
        $email = (string) $this->option('admin');
        $admin = User::where('email', $email)->first();
        if (! $admin) {
            $this->error("Super-admin user not found: {$email}");
            return self::FAILURE;
        }
        $this->admin = $admin;

        if ($this->option('fresh')) {
            $this->warn('Purging existing demo data first…');
            $this->call('demo:purge', ['--force' => true]);
        }
        if (DemoManifest::exists()) {
            $this->error('A demo manifest already exists. Run `php artisan demo:purge` first, or pass --fresh.');
            return self::FAILURE;
        }

        $this->roleIds = DB::table('roles')->pluck('id', 'slug')->all();
        $this->m = new DemoManifest();

        $clubCount   = max(1, (int) $this->option('clubs'));
        $perClub     = max(1, (int) $this->option('members'));
        $blueprints  = array_slice($this->blueprints(), 0, $clubCount);

        $this->info("Seeding demo: {$clubCount} clubs × {$perClub} members, wired to {$email}…");

        DB::transaction(function () use ($blueprints, $perClub) {
            // Business / chain owned by the super admin.
            $business = Business::create([
                'owner_user_id' => $this->admin->id,
                'name' => 'TAKEONE Demo Group',
                'slug' => 'demo-takeone-group',
                'description' => 'Demo chain grouping all showcase clubs.',
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->admin->id,
            ]);
            $this->m->track('businesses', $business->id);

            $allClubs = [];
            foreach ($blueprints as $i => $bp) {
                $allClubs[] = $this->buildClub($bp, $i, $business->id, $perClub);
            }

            // Wire the super admin's own /me experience across the demo clubs.
            $this->wireSuperAdmin($allClubs);
        });

        $this->m->save();

        $t = $this->m->totals();
        $this->newLine();
        $this->info('✅ Demo seeded. Summary:');
        foreach ($t as $table => $n) {
            $this->line('   ' . str_pad($table, 28) . $n);
        }
        $this->newLine();
        $this->info('Manifest: ' . DemoManifest::path());
        $this->info('Remove everything later with:  php artisan demo:purge');
        return self::SUCCESS;
    }

    /** Build one fully-populated club and return a context array. */
    private function buildClub(array $bp, int $idx, int $businessId, int $perClub): array
    {
        // Bahrain-ish coordinates, jittered per club.
        $lat = 26.10 + ($idx * 0.03);
        $lng = 50.50 + ($idx * 0.03);

        $club = Tenant::create([
            'owner_user_id' => $this->admin->id,
            // business_id is set automatically by Tenant::creating() from the owner's approved business.
            'club_name'     => $bp['name'],
            'slug'          => 'demo-' . $bp['slug'],
            'slogan'        => $bp['slogan'],
            'description'   => "{$bp['name']} — a demo {$bp['sport']} club showcasing the TAKEONE platform.",
            'country'       => 'BH',                 // ISO-2 code — the public club URL is /{country}/clubs/{slug} with country [a-z]{2,3}
            'currency'      => 'BHD',
            'timezone'      => 'Asia/Bahrain',
            'enrollment_fee' => [0, 5, 10, 15][$idx % 4],
            'email'         => 'info@demo-' . $bp['slug'] . '.test',
            'phone'         => ['code' => '+973', 'number' => '3' . str_pad((string) (1000000 + $idx), 7, '0')],
            'gps_lat'       => $lat,
            'gps_long'      => $lng,
            'address'       => 'Building ' . (100 + $idx) . ', Manama, Bahrain',
            'status'        => 'active',             // match real live clubs (eta/lta/pta)
            'public_profile_enabled' => true,
        ]);
        $this->m->track('tenants', $club->id);
        $this->grantRole($this->admin->id, 'club-admin', $club->id);

        // Facility
        $facility = ClubFacility::create([
            'tenant_id' => $club->id, 'name' => 'Main Hall', 'address' => $club->address,
            'gps_lat' => $lat, 'gps_long' => $lng, 'is_available' => true,
        ]);
        $this->m->track('club_facilities', $facility->id);

        // Trainers (demo users + ClubInstructor rows)
        $instructors = [];
        $nTrainers = 4;
        for ($t = 0; $t < $nTrainers; $t++) {
            $u = $this->demoUser('coach', "{$bp['sport']} coach", true, $bp['sport']);
            $ci = ClubInstructor::create([
                'tenant_id' => $club->id, 'user_id' => $u->id, 'role' => $bp['role'],
                'rating' => round(4.2 + ($t * 0.15), 1), 'sort_order' => $t,
                'compensation_type' => ClubInstructor::COMPENSATION_PAID,
                'wage_amount' => 200 + $t * 50, 'wage_period' => 'monthly',
            ]);
            $this->m->track('club_instructors', $ci->id);
            $this->grantRole($u->id, 'instructor', $club->id);
            $instructors[] = $ci;
        }

        // Activities
        $activities = [];
        $days = ['saturday','sunday','monday','tuesday','wednesday','thursday'];
        foreach ($bp['activities'] as $ai => $aname) {
            $act = ClubActivity::create([
                'tenant_id' => $club->id, 'name' => $aname,
                'duration_minutes' => 60, 'frequency_per_week' => 3, 'facility_id' => $facility->id,
                'description' => "{$aname} at {$bp['name']}.",
            ]);
            $this->m->track('club_activities', $act->id);
            $activities[] = $act;
        }

        // Packages (+ link activities with weekly schedule slots)
        $packages = [];
        foreach ($bp['packages'] as $pi => [$pname, $price, $ageMin, $ageMax, $gender]) {
            $pkg = ClubPackage::create([
                'tenant_id' => $club->id, 'name' => $pname, 'type' => 'single', 'price' => $price,
                'duration_months' => 1, 'session_count' => 0, 'age_min' => $ageMin, 'age_max' => $ageMax,
                'gender' => $gender, 'description' => "{$pname} membership at {$bp['name']}.", 'is_active' => true,
            ]);
            $this->m->track('club_packages', $pkg->id);

            // Each package gets 1-2 activities with 2-3 weekly slots.
            $act = $activities[$pi % count($activities)];
            $instr = $instructors[$pi % count($instructors)];
            $pa = ClubPackageActivity::create([
                'package_id' => $pkg->id, 'activity_id' => $act->id, 'instructor_id' => $instr->id,
            ]);
            $slotDays = array_slice($days, $pi % 3, 3);
            $start = ['16:00','17:30','18:00','06:30','19:00','07:00'][$pi % 6];
            $end   = date('H:i', strtotime($start) + 3600);
            $slots = array_map(fn ($d) => [
                'day' => $d, 'start_time' => $start, 'end_time' => $end,
                'facility_id' => (string) $facility->id, 'facility_name' => $facility->name,
            ], $slotDays);
            $pa->forceFill(['schedule' => $slots])->save();
            $this->m->track('club_package_activities', $pa->id);

            $packages[] = $pkg;
        }

        // Members + active subscriptions
        $members = [];
        for ($mi = 0; $mi < $perClub; $mi++) {
            $u = $this->demoUser('member', 'member');
            $mem = Membership::create(['tenant_id' => $club->id, 'user_id' => $u->id, 'status' => 'active']);
            $this->m->track('memberships', $mem->id);
            $this->grantRole($u->id, 'member', $club->id);

            $pkg = $packages[$mi % count($packages)];
            $sub = ClubMemberSubscription::create([
                'tenant_id' => $club->id, 'type' => 'regular', 'user_id' => $u->id, 'package_id' => $pkg->id,
                'start_date' => now()->subDays(rand(1, 90))->toDateString(),
                'end_date' => now()->addMonths(1)->toDateString(),
                'status' => 'active', 'payment_status' => 'paid',
                'amount_paid' => $pkg->price, 'amount_due' => 0,
            ]);
            $this->m->track('club_member_subscriptions', $sub->id);

            // Income transaction so the club + chain dashboards show real revenue.
            $tx = ClubTransaction::create([
                'tenant_id' => $club->id, 'user_id' => $u->id, 'type' => 'income', 'category' => 'subscription',
                'amount' => $pkg->price, 'payment_method' => 'cash', 'description' => 'Membership payment',
                'transaction_date' => now()->subDays(rand(0, 175)), 'subscription_id' => $sub->id,
            ]);
            $this->m->track('club_transactions', $tx->id);

            $members[] = $u;
        }

        // A few recurring expenses per club for a realistic net.
        foreach ([['rent', 'Facility rent', 400], ['salaries', 'Coach wages', 650], ['equipment', 'Equipment & supplies', 120]] as [$cat, $desc, $base]) {
            foreach ([20, 80] as $daysAgo) {
                $ex = ClubTransaction::create([
                    'tenant_id' => $club->id, 'type' => 'expense', 'category' => $cat,
                    'amount' => $base + rand(0, 60), 'payment_method' => 'bank_transfer', 'description' => $desc,
                    'transaction_date' => now()->subDays($daysAgo + rand(0, 10)),
                ]);
                $this->m->track('club_transactions', $ex->id);
            }
        }

        $this->seedTimeline($club, $bp, $instructors, $members);
        $this->seedChallenges($club, $bp, $members);
        $this->seedEvents($club, $bp, $members);
        $this->seedShop($club, $bp);

        return compact('club', 'bp', 'instructors', 'activities', 'packages', 'members');
    }

    private function seedTimeline(Tenant $club, array $bp, array $instructors, array $members): void
    {
        $posts = [
            ['Match Day', "🏆 What a day for {$bp['name']}! Huge effort from everyone who competed. Proud of this team.", 'bi-trophy-fill'],
            ['Tip of the day', 'Consistency beats intensity. Show up, do the work, repeat. 💪', 'bi-lightbulb-fill'],
            ['Event', "📣 New session times are live for {$bp['sport']} — check the schedule and book your spot.", 'bi-megaphone-fill'],
            ['Milestone', 'Big shoutout to our members hitting new personal bests this week! 🔥', 'bi-stars'],
            ['Community', 'Thank you to everyone who came down this weekend. The energy was unreal. 🙌', 'bi-people-fill'],
        ];
        foreach ($posts as $h => [$cat, $body, $icon]) {
            $post = ClubTimelinePost::create([
                'tenant_id' => $club->id, 'body' => $body, 'category' => $cat, 'status' => 'published',
                'posted_at' => now()->subHours(($h + 1) * 7),
                'cover' => ['color' => $bp['color'], 'icon' => $icon, 'label' => $cat],
            ]);
            $this->m->track('club_timeline_posts', $post->id);

            foreach (array_slice($members, 0, rand(1, 2)) as $cm) {
                $c = ClubTimelinePostComment::create(['post_id' => $post->id, 'user_id' => $cm->id, 'body' => ['Love this! 🙌','So good 🔥','Counting me in!'][rand(0, 2)]]);
                $this->m->track('club_timeline_post_comments', $c->id);
            }
            foreach (array_slice($members, 0, min(count($members), rand(8, 18))) as $lm) {
                $l = ClubTimelinePostLike::create(['post_id' => $post->id, 'user_id' => $lm->id]);
                $this->m->track('club_timeline_post_likes', $l->id);
            }
        }
    }

    private function seedChallenges(Tenant $club, array $bp, array $members): void
    {
        $defs = [
            ['30-Day Consistency', 'attendance', 30, 'sessions', 'bi-calendar-check'],
            ['Step Master', 'steps', 200000, 'steps', 'bi-activity'],
            ['Early Bird Club', 'attendance', 12, 'mornings', 'bi-sunrise'],
            ['Personal Best Push', 'performance', 5, 'PBs', 'bi-graph-up-arrow'],
        ];
        foreach ($defs as [$title, $metric, $goal, $unit, $icon]) {
            $ch = Challenge::create([
                'tenant_id' => $club->id, 'created_by' => $this->admin->id, 'title' => $title,
                'tag' => $bp['sport'], 'category' => 'fitness', 'icon' => $icon, 'color' => $bp['color'],
                'metric' => $metric, 'goal' => $goal, 'unit' => $unit, 'points' => 100,
                'starts_at' => now()->subDays(7), 'ends_at' => now()->addDays(23),
                'about' => "Join the {$title} challenge at {$bp['name']}!", 'is_active' => true,
            ]);
            $this->m->track('challenges', $ch->id);

            foreach (array_slice($members, 0, min(count($members), rand(6, 12))) as $pm) {
                $p = ChallengeParticipation::create([
                    'challenge_id' => $ch->id, 'user_id' => $pm->id,
                    'progress' => rand(0, $goal), 'streak' => rand(0, 14),
                ]);
                $this->m->track('challenge_participations', $p->id);
            }
        }
    }

    private function seedEvents(Tenant $club, array $bp, array $members): void
    {
        $defs = [
            ['Open Day & Trials', now()->addDays(10), 'open', 'social'],
            [$bp['sport'] . ' Club Championship', now()->addDays(24), 'competition', 'competition'],
            ['Summer Intensive Camp', now()->addDays(40), 'camp', 'camp'],
        ];
        foreach ($defs as [$title, $date, $type, $scope]) {
            $ev = ClubEvent::create([
                'tenant_id' => $club->id, 'title' => $title,
                'description' => "{$title} hosted by {$bp['name']}.",
                'date' => $date->toDateString(), 'start_time' => '10:00', 'end_time' => '16:00',
                'location' => $club->address, 'gps_lat' => $club->gps_lat, 'gps_long' => $club->gps_long,
                'level' => 'all', 'max_capacity' => 100, 'spots_taken' => 0,
                'ribbon_label' => ucfirst($type), 'ribbon_type' => 'info',
                'color' => $bp['color'], 'cta_text' => 'Register', 'status' => 'published',
                'scope' => $scope, 'event_type' => $type, 'sport' => $bp['sport'], 'icon' => $bp['icon'],
                'uuid' => (string) Str::uuid(),
            ]);
            $this->m->track('club_events', $ev->id);

            $regs = array_slice($members, 0, min(count($members), rand(10, 25)));
            foreach ($regs as $rm) {
                $r = ClubEventRegistration::create([
                    'event_id' => $ev->id, 'user_id' => $rm->id,
                    'role' => 'participant', 'status' => 'confirmed', 'paid' => true,
                    'registered_at' => now()->subDays(rand(1, 5)),
                ]);
                $this->m->track('club_event_registrations', $r->id);
            }
            $ev->update(['spots_taken' => count($regs)]);
        }
    }

    private function seedShop(Tenant $club, array $bp): void
    {
        $cats = [['gear', 'Gear', 'bi-bag'], ['apparel', 'Apparel', 'bi-person-arms-up'], ['nutrition', 'Nutrition', 'bi-cup-straw']];
        foreach ($cats as $si => [$key, $label, $icon]) {
            $cat = ClubProductCategory::create(['tenant_id' => $club->id, 'key' => $key, 'label' => $label, 'icon' => $icon, 'sort' => $si]);
            $this->m->track('club_product_categories', $cat->id);
        }
        $products = [
            ['Club Training Tee', 'apparel', 8, 'bi-person-arms-up'],
            ['Official Hoodie', 'apparel', 18, 'bi-person-arms-up'],
            ["{$bp['sport']} Starter Kit", 'gear', 25, 'bi-bag-fill'],
            ['Water Bottle 750ml', 'gear', 4, 'bi-cup-straw'],
            ['Protein Shake', 'nutrition', 6, 'bi-cup-straw'],
            ['Gym Towel', 'gear', 5, 'bi-bag'],
        ];
        foreach ($products as $pi => [$name, $cat, $price, $icon]) {
            $p = ClubProduct::create([
                'tenant_id' => $club->id, 'name' => $name, 'brand' => $bp['name'], 'category' => $cat,
                'price' => $price, 'availability' => 'in_stock', 'featured' => $pi < 2,
                'color' => $bp['color'], 'icon' => $icon, 'description' => "{$name} from {$bp['name']}.",
                'quantity' => rand(10, 80), 'status' => 'active', 'sort' => $pi,
            ]);
            $this->m->track('club_products', $p->id);
        }
    }

    /** Populate the super admin's own /me views: enrol, teach, personal sessions, feed, challenges, events. */
    private function wireSuperAdmin(array $clubs): void
    {
        $admin = $this->admin;

        // 1) Enrol in two packages (different clubs) → synced classes on /me/schedule.
        foreach (array_slice($clubs, 0, 2) as $ctx) {
            $pkg = $ctx['packages'][0];
            Membership::firstOrCreate(['tenant_id' => $ctx['club']->id, 'user_id' => $admin->id], ['status' => 'active'])
                ->wasRecentlyCreated and $this->m->track('memberships', Membership::where('tenant_id', $ctx['club']->id)->where('user_id', $admin->id)->value('id'));
            $sub = ClubMemberSubscription::create([
                'tenant_id' => $ctx['club']->id, 'type' => 'regular', 'user_id' => $admin->id, 'package_id' => $pkg->id,
                'start_date' => now()->subDays(10)->toDateString(), 'end_date' => now()->addMonths(1)->toDateString(),
                'status' => 'active', 'payment_status' => 'paid', 'amount_paid' => $pkg->price, 'amount_due' => 0,
            ]);
            $this->m->track('club_member_subscriptions', $sub->id);
            $tx = ClubTransaction::create([
                'tenant_id' => $ctx['club']->id, 'user_id' => $admin->id, 'type' => 'income', 'category' => 'subscription',
                'amount' => $pkg->price, 'payment_method' => 'cash', 'description' => 'Membership payment',
                'transaction_date' => now()->subDays(rand(0, 60)), 'subscription_id' => $sub->id,
            ]);
            $this->m->track('club_transactions', $tx->id);
        }

        // 2) Make the super admin a coach in the first club → teaching classes + Coach tools.
        $first = $clubs[0];
        $ci = ClubInstructor::firstOrCreate(
            ['tenant_id' => $first['club']->id, 'user_id' => $admin->id],
            ['role' => 'Head Coach', 'rating' => 5.0, 'sort_order' => 99, 'compensation_type' => ClubInstructor::COMPENSATION_VOLUNTEER]
        );
        $this->m->track('club_instructors', $ci->id);
        // Assign the admin as instructor on one of that club's package activities.
        $pa = ClubPackageActivity::where('package_id', $first['packages'][0]->id)->first();
        if ($pa) { $pa->update(['instructor_id' => $ci->id]); }

        // 3) Personal schedule sessions.
        $sessions = [
            ['monday', '06:00', '07:00', 'Morning Strength', 'Strength Training', 'bi-trophy', 'High', ['Legs','Core']],
            ['tuesday', '18:30', '19:30', 'Evening Run', 'Cardio', 'bi-activity', 'Moderate', ['Endurance']],
            ['thursday', '07:00', '08:00', 'Mobility Flow', 'Recovery', 'bi-flower1', 'Low', ['Flexibility']],
            ['saturday', '09:00', '10:30', 'Long Session', 'Conditioning', 'bi-fire', 'High', ['Full body']],
        ];
        foreach ($sessions as [$day, $st, $et, $title, $disc, $icon, $intensity, $focus]) {
            $s = UserScheduleSession::create([
                'user_id' => $admin->id, 'subject_user_id' => $admin->id, 'day' => $day,
                'start_time' => $st, 'end_time' => $et, 'title' => $title, 'discipline' => $disc,
                'icon' => $icon, 'color' => '#7c3aed', 'location' => 'Home Gym', 'intensity' => $intensity,
                'focus' => $focus,
                // warmup/cooldown are plain strings; main is a list of {name,sets,reps,note?} (see schedule-show.blade.php).
                'workout' => [
                    'warmup' => ['5 min light cardio', 'Dynamic stretching'],
                    'main' => [
                        ['name' => 'Primary lift', 'sets' => 4, 'reps' => 8, 'note' => 'Controlled tempo'],
                        ['name' => 'Accessory work', 'sets' => 3, 'reps' => 12],
                        ['name' => 'Finisher', 'sets' => 2, 'reps' => 15],
                    ],
                    'cooldown' => ['Foam rolling', '5 min full-body stretch'],
                ],
            ]);
            $this->m->track('user_schedule_sessions', $s->id);
        }

        // 4) Feed: own posts (text, highlight, poll) + stories + follow some coaches.
        $p1 = UserPost::create(['user_id' => $admin->id, 'type' => 'text', 'body' => 'Great week across all the clubs — proud of every coach and member. Onwards! 🚀']);
        $p1->forceFill(['created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)])->saveQuietly();
        $this->m->track('user_posts', $p1->id);
        $p2 = UserPost::create(['user_id' => $admin->id, 'type' => 'highlight', 'body' => 'New bench PR — 100kg for 3. 💪', 'cover' => ['color' => '#7c3aed', 'icon' => 'bi-trophy-fill', 'label' => 'Bench PR · 100kg']]);
        $p2->forceFill(['created_at' => now()->subHours(20), 'updated_at' => now()->subHours(20)])->saveQuietly();
        $this->m->track('user_posts', $p2->id);
        $p3 = UserPost::create(['user_id' => $admin->id, 'type' => 'poll', 'body' => 'Which new class should we add platform-wide?', 'poll' => ['question' => 'Which new class should we add platform-wide?', 'options' => ['Mobility', 'Olympic lifting', 'Boxing fundamentals', 'Sunrise yoga']]]);
        $p3->forceFill(['created_at' => now()->subHours(8), 'updated_at' => now()->subHours(8)])->saveQuietly();
        $this->m->track('user_posts', $p3->id);

        $st1 = UserStory::create(['user_id' => $admin->id, 'type' => 'text', 'caption' => 'On the road visiting clubs today! 🚗', 'color' => '#0ea5e9', 'icon' => 'bi-geo-alt']);
        $this->m->track('user_stories', $st1->id);

        // Follow + engage with the first coaches of each club.
        foreach ($clubs as $ctx) {
            $coach = $ctx['instructors'][0];
            $fid = DB::table('user_follows')->insertGetId(['follower_id' => $admin->id, 'followee_id' => $coach->user_id, 'created_at' => now(), 'updated_at' => now()]);
            $this->m->track('user_follows', $fid);
        }

        // 5) Challenges: join a few + a duel.
        $someChallenges = Challenge::whereIn('tenant_id', collect($clubs)->pluck('club.id'))->inRandomOrder()->limit(5)->get();
        foreach ($someChallenges as $ch) {
            $p = ChallengeParticipation::create(['challenge_id' => $ch->id, 'user_id' => $admin->id, 'progress' => rand(3, (int) $ch->goal), 'streak' => rand(2, 10)]);
            $this->m->track('challenge_participations', $p->id);
        }
        $opponent = $clubs[0]['members'][0] ?? null;
        if ($opponent) {
            $d = Duel::create([
                'challenger_id' => $admin->id, 'opponent_id' => $opponent->id, 'opponent_name' => $opponent->full_name,
                'type' => 'head-to-head', 'discipline' => 'Strength', 'metric' => 'Total reps', 'stake_points' => 50,
                'deadline' => now()->addDays(7), 'status' => 'active', 'message' => 'Loser buys the smoothies 🥤',
            ]);
            $this->m->track('duels', $d->id);
        }

        // 6) Events: register the admin into a few.
        $someEvents = ClubEvent::whereIn('tenant_id', collect($clubs)->pluck('club.id'))->inRandomOrder()->limit(4)->get();
        foreach ($someEvents as $ev) {
            $r = ClubEventRegistration::create(['event_id' => $ev->id, 'user_id' => $admin->id, 'role' => 'participant', 'status' => 'confirmed', 'paid' => true, 'registered_at' => now()->subDays(2)]);
            $this->m->track('club_event_registrations', $r->id);
        }
    }

    /** Create a tagged demo user. */
    private function demoUser(string $kind, string $label, bool $trainer = false, ?string $sport = null): User
    {
        $fn = $this->firstNames()[array_rand($this->firstNames())];
        $ln = $this->lastNames()[array_rand($this->lastNames())];
        $name = "{$fn} {$ln}";
        $handle = $kind . '.' . Str::random(10);
        $data = [
            'full_name' => $name, 'name' => $name,
            'email' => $handle . '@demo.takeone.bh',
            'password' => Hash::make(Str::random(24)),
            'email_verified_at' => now(),
            'gender' => rand(0, 1) ? 'm' : 'f',
            'nationality' => 'Bahrain',
            'birthdate' => now()->subYears(rand(8, 45))->subDays(rand(0, 365))->toDateString(),
            'mobile' => ['code' => '+973', 'number' => (string) rand(30000000, 39999999)],
        ];
        if ($trainer) {
            $data['is_personal_trainer'] = true;
            $data['experience_years'] = rand(2, 18);
            $data['bio'] = "Experienced {$sport} coach passionate about helping members progress.";
            $data['skills'] = [$sport, 'Coaching', 'Conditioning'];
        }
        $u = User::create($data);
        $this->m->track('users', $u->id);
        return $u;
    }

    /** Insert a user_roles row (tenant-scoped) and record it. */
    private function grantRole(int $userId, string $slug, int $tenantId): void
    {
        $roleId = $this->roleIds[$slug] ?? null;
        if (! $roleId) return;
        $exists = DB::table('user_roles')->where(['user_id' => $userId, 'role_id' => $roleId, 'tenant_id' => $tenantId])->exists();
        if ($exists) return;
        $id = DB::table('user_roles')->insertGetId(['user_id' => $userId, 'role_id' => $roleId, 'tenant_id' => $tenantId, 'created_at' => now(), 'updated_at' => now()]);
        $this->m->track('user_roles', $id);
    }
}
