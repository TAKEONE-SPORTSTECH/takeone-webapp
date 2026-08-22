<?php

namespace App\Console\Commands;

use App\Events\EventTypeRegistry;
use App\Models\ClubAffiliation;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\HealthRecord;
use App\Models\MemberCertification;
use App\Models\SkillAcquisition;
use App\Models\Tenant;
use App\Models\User;
use App\Sports\Combat\SportRegistry;
use App\Support\DemoManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A competition you can actually run, for demonstrating the hall screens.
 *
 * The court display, the upcoming-matches board and the scoreboard all draw
 * things the platform has to already know: a competitor's height and weight, the
 * belt they were graded to, the club they train at, the division they belong in,
 * and a draw that pairs them. Seeding a name and a weight class is not enough —
 * an empty stat line is exactly the failure mode these screens are supposed to
 * expose, so the demo data has to be as complete as a real entrant's.
 *
 * So each athlete gets a HISTORY, not a snapshot:
 *   · dated health records, so weight has a trend and the enrolment gate has
 *     something to classify (Enrolment::declaredWeight reads the latest one)
 *   · a club affiliation with a skill in the sport, carrying a proficiency
 *     level — BeltRank's third source
 *   · a ladder of belt certifications ending at their current grade — BeltRank's
 *     second source, and the one the arena screen usually announces
 *   · a weigh-in on the day, recording the weight AND the belt presented —
 *     BeltRank's first source, which outranks both of the above
 *
 * Two events, one per sport, because the packages are separate and the point of
 * the demo is that each runs its own screens off its own draw.
 *
 * ADDITIVE AND REMOVABLE. It never truncates anything and never touches a row it
 * did not create: every id is written to its own manifest (separate from
 * demo:seed's, so purging one cannot delete the other's rows) and
 * `demo:competition --purge` removes exactly that set.
 */
class DemoCompetition extends Command
{
    protected $signature = 'demo:competition
        {--athletes=24 : Athletes per sport}
        {--admin=superadmin@takeone.bh : Who owns the events}
        {--fresh : Purge a previous run first}
        {--purge : Remove everything this command created, and stop}';

    protected $description = 'Seed two runnable competitions — athletes with physique, skills and belts — for demonstrating the hall screens';

    private const MANIFEST = 'competition';

    /** Demo accounts are recognisable at a glance and never collide with real ones. */
    private const EMAIL_DOMAIN = '@demo.takeone.bh';

    private DemoManifest $m;

    private User $admin;

    public function handle(): int
    {
        if ($this->option('purge')) {
            return $this->purge();
        }

        $this->admin = User::where('email', (string) $this->option('admin'))->first();
        if (! $this->admin) {
            $this->error('Owner not found: '.$this->option('admin'));

            return self::FAILURE;
        }

        if (DemoManifest::exists(self::MANIFEST)) {
            if (! $this->option('fresh')) {
                $this->error('A competition demo already exists. Re-run with --fresh, or remove it with --purge.');

                return self::FAILURE;
            }
            $this->purge();
        }

        $this->m = new DemoManifest(self::MANIFEST);
        $perSport = max(8, (int) $this->option('athletes'));

        $sports = [
            'karate' => [
                'title' => 'Gulf Karate Open 2026',
                'type' => 'tournament',
                'fee' => 15,
                'colour' => '#b3121f',
                'venue' => 'Isa Sports City · Hall 2',
                // Club => the country the club is REGISTERED in. This is the
                // flag its competitors fly, whatever passports they hold.
                'clubs' => ['Budokan Elite Dojo' => 'BH', 'Al Hala Karate Club' => 'QA',
                    'Manama Shotokan' => 'OM', 'Riffa Fight Academy' => 'SA'],
                // One rung = one (colour, grade) pair. Kept together because
                // they are not independent: "Black Belt · 1st Kyu" is a
                // contradiction — kyu grades are the coloured belts BELOW black.
                'ladder' => [
                    ['White', '9th Kyu'], ['Yellow', '8th Kyu'], ['Orange', '7th Kyu'],
                    ['Green', '6th Kyu'], ['Blue', '4th Kyu'], ['Brown', '2nd Kyu'],
                    ['Black', '1st Dan'], ['Black', '2nd Dan'], ['Black', '3rd Dan'],
                ],
                'issuer' => 'Bahrain Karate Federation',
                'official' => 'Sami Kooheji',
                'courts' => 3,
                // Each package owns its own fleet: own devices table, own routes.
                'device' => \App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayDevice::class,
                'table' => 'karate_court_displays',
                'court_path' => '/karate/court/',
            ],
            'taekwondo' => [
                'title' => 'Gulf Taekwondo Grand Prix 2026',
                'type' => 'championship',
                'fee' => 20,
                'colour' => '#0d55b8',
                'venue' => 'Khalifa Sports City · Isa Town',
                'clubs' => ['Tiger Taekwondo Academy' => 'BH', 'Falcon Dojang' => 'SA',
                    'Muharraq TKD Centre' => 'AE', 'Sitra Black Belt Club' => 'KW'],
                // Same rule: gup grades belong to the coloured belts, dan to black.
                'ladder' => [
                    ['White', '10th Gup'], ['Yellow', '8th Gup'], ['Green', '6th Gup'],
                    ['Blue', '4th Gup'], ['Red', '2nd Gup'], ['Red', '1st Gup'],
                    ['Black', '1st Dan'], ['Black', '2nd Dan'],
                ],
                'issuer' => 'Bahrain Taekwondo Federation',
                'official' => 'Nader Alwadi',
                'courts' => 3,
                'device' => \App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayDevice::class,
                'table' => 'court_displays',
                'court_path' => '/court/',
            ],
        ];

        $summary = [];

        DB::transaction(function () use ($sports, $perSport, &$summary) {
            foreach ($sports as $sportKey => $bp) {
                $summary[] = $this->buildCompetition($sportKey, $bp, $perSport);
            }
        });

        $this->m->save();

        $this->newLine();
        $this->info('✅ Competition demo seeded.');
        foreach ($summary as $s) {
            $this->newLine();
            $this->line("   <options=bold>{$s['title']}</>");
            $this->line('     sport        '.$s['sport']);
            $this->line('     athletes     '.$s['athletes']);
            $this->line('     divisions    '.$s['divisions']);
            $this->line('     bouts drawn  '.$s['matches']);
            $this->line('     console      '.$s['console']);
            foreach ($s['screens'] as $mat => $url) {
                $this->line('     '.str_pad($mat, 13).$url);
            }
        }
        $this->newLine();
        foreach ($this->m->totals() as $table => $n) {
            $this->line('   '.str_pad($table, 28).$n);
        }
        $this->newLine();
        $this->info('Manifest: '.DemoManifest::path(self::MANIFEST));
        $this->info('Remove it all with:  php artisan demo:competition --purge');

        return self::SUCCESS;
    }

    /** One sport: its athletes, its event, its divisions, its draw. */
    private function buildCompetition(string $sportKey, array $bp, int $count): array
    {
        $sport = app(SportRegistry::class)->get($sportKey);
        $this->info("Seeding {$bp['title']}…");

        $clubs = $this->makeClubs($bp, $sportKey);
        $event = $this->makeEvent($sportKey, $bp);

        // A jury, because the arena screen announces one and an event with no
        // appointed official hides the chip — correct, but it means the demo
        // never shows that half of the design.
        // A person, not the platform account: the arena screen abbreviates the
        // jury to "S. Petrov" form, and "Super Administrator" is long enough to
        // wrap the centred chip row onto two lines.
        $official = User::create([
            'full_name' => $bp['official'], 'name' => $bp['official'],
            'email' => Str::slug($bp['official']).'-official'.self::EMAIL_DOMAIN,
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(), 'gender' => 'Male',
            'nationality' => 'BH', 'is_discoverable' => false,
        ]);
        $this->m->track('users', $official->id);

        $jury = \App\Models\EventOfficial::create([
            'event_id' => $event->id,
            'user_id' => $official->id,
            'role' => \App\Models\EventOfficial::ROLE_JURY,
            'assigned_by' => $this->admin->id,
        ]);
        $this->m->track('event_officials', $jury->id);
        $athletes = $this->makeAthletes($sportKey, $bp, $count, $sport, $clubs);

        // Divisions come from who actually entered: classify every athlete, then
        // create exactly the divisions they land in. An event carrying divisions
        // nobody is in would draw a board full of empty brackets.
        $wanted = [];
        foreach ($athletes as $a) {
            if ($class = $sport->classify($a['user']->gender, $a['age'], $a['weight'])) {
                $wanted[$sport->divisionName($class['age_group'], $a['user']->gender, $class['category'])] = $class['category'];
            }
        }

        $categories = [];
        $order = 0;
        foreach ($wanted as $name => $weightClass) {
            $cat = EventCategory::create([
                'event_id' => $event->id,
                'name' => $name,
                'weight_class' => $weightClass.' kg',
                'status' => 'enrolling',
                'sort_order' => ++$order,
            ]);
            $this->m->track('event_categories', $cat->id);
            $categories[$name] = $cat;
        }

        // Enrol, pay, and weigh in — the three gates a competitor passes before
        // they can be drawn. The weigh-in records the belt as well as the
        // weight, which is what the arena screen announces.
        foreach ($athletes as $a) {
            $class = $sport->classify($a['user']->gender, $a['age'], $a['weight']);
            if (! $class) {
                continue;
            }
            $name = $sport->divisionName($class['age_group'], $a['user']->gender, $class['category']);
            $cat = $categories[$name] ?? null;

            $reg = ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $a['user']->id,
                'role' => 'participant',
                'status' => 'joined',
                'category_id' => $cat?->id,
                'weight' => $a['weight'],
                'belt_colour' => $a['belt_colour'],
                'belt_grade' => $a['belt_grade'],
                'weighed_in_at' => now()->subHours(3),
                'weighed_in_by' => $this->admin->id,
                'paid' => true,
                'paid_at' => now()->subDay(),
                'paid_by' => $this->admin->id,
                // The flag is the CLUB's country, never the athlete's own —
                // they are here as their club. Their nationality stays on their
                // profile, and is deliberately seeded to differ from it.
                'meta' => $a['club']->country,
                'registered_at' => now()->subDays(random_int(3, 20)),
                'entered_by' => $this->admin->id,
                'entry_channel' => 'club',
                'representing_tenant_id' => $a['club']->id,
            ]);
            $this->m->track('club_event_registrations', $reg->id);
        }

        // The draw itself, through the package's own action — the demo must
        // exercise the real engine, not hand-write brackets it would never
        // produce.
        $result = app(EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');
        if (! ($result['success'] ?? false)) {
            $this->warn('   draw: '.($result['message'] ?? 'failed'));
        }

        foreach ($event->matches()->pluck('id') as $id) {
            $this->m->track('event_matches', $id);
        }

        // A screen per mat, already paired, so the boards are openable in a
        // browser the moment this finishes. Pairing is normally a Pi enrolling
        // and an organiser scanning its code; issue() is the same end state
        // reached directly, and it hands back the one plaintext token that will
        // ever exist for that screen — which is why the URLs are printed here
        // and nowhere else.
        $screens = [];
        foreach ($event->matches()->whereNotNull('court')->distinct()->orderBy('court')->pluck('court') as $mat) {
            $issued = $bp['device']::issue($event, $mat, $this->admin->id, 'Demo · '.$mat);
            $this->m->track($bp['table'], $issued['device']->id);
            $screens[$mat] = rtrim(config('app.url'), '/').$bp['court_path'].$issued['token'];
        }

        return [
            'title' => $event->title,
            'sport' => $sportKey,
            'athletes' => count($athletes),
            'divisions' => count($categories),
            'matches' => $event->matches()->count(),
            'console' => url('/me/events/'.$event->uuid),
            'screens' => $screens,
        ];
    }

    /**
     * The clubs the athletes compete for, as real tenants.
     *
     * They have to be tenants rather than free-text affiliations because the
     * hall board reads a competitor's club from memberClubs — it wants a crest
     * and a country to fly, and a string cannot carry either. Slug-prefixed
     * `demo-` so they are recognisable next to real clubs at a glance.
     *
     * @return array<int, Tenant>
     */
    private function makeClubs(array $bp, string $sportKey): array
    {
        $clubs = [];

        foreach ($bp['clubs'] as $name => $country) {
            $club = Tenant::create([
                'owner_user_id' => $this->admin->id,
                'club_name' => $name,
                'slug' => 'demo-'.$sportKey.'-'.Str::slug($name),
                // Spread across the Gulf on purpose: a competition prints the
                // CLUB's country beside a competitor, so a demo where every
                // club sits in one country proves nothing.
                'country' => $country,
                'description' => 'Demo club seeded by demo:competition — safe to delete.',
            ]);
            $this->m->track('tenants', $club->id);
            $clubs[] = $club;
        }

        return $clubs;
    }

    /** The event itself: today, so it is live while you are demonstrating it. */
    private function makeEvent(string $sportKey, array $bp): ClubEvent
    {
        $event = ClubEvent::create([
            'tenant_id' => $this->admin->memberClubs()->value('tenants.id') ?? \App\Models\Tenant::value('id'),
            'title' => $bp['title'],
            'description' => 'Demo competition seeded by demo:competition — safe to delete.',
            'date' => now()->startOfDay(),
            'end_date' => now()->addDay()->startOfDay(),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'location' => $bp['venue'],
            'sport' => $sportKey,
            'event_type' => $bp['type'],
            // Worldwide, not nationwide: the entrant clubs sit in five
            // countries, and a nationwide event only reaches the host's own.
            'scope' => 'worldwide',
            'status' => 'active',
            'color' => $bp['colour'],
            'participant_fee' => 'BHD '.$bp['fee'],
            'participant_fee_amount' => $bp['fee'],
            'fee_currency' => 'BHD',
            // Entries close the day before the competition, which is both how a
            // real one runs and what the create form enforces (a closing date
            // after the event date is refused there). Reopen it by editing the
            // event if you want to demonstrate entering a squad.
            'enrollment_ends_at' => now()->subDay()->startOfDay(),
            'courts' => $bp['courts'],
            'minutes_per_match' => 8,
            'created_by' => $this->admin->id,
            'weigh_in_at' => now()->subHours(4),
        ]);

        $this->m->track('club_events', $event->id);

        return $event;
    }

    /**
     * Athletes, with the history that makes them enterable.
     *
     * Weights are drawn tightly around a few band centres rather than uniformly
     * across the range: a real entry list clusters into divisions, and a
     * uniformly random one produces twenty divisions of one person, which draws
     * a board of byes and demonstrates nothing.
     *
     * The bands come from THE SPORT'S OWN senior table, never from a hard-coded
     * list — karate runs five senior classes and taekwondo eight, so a list
     * tuned to one scatters the other across a dozen divisions of two people.
     */
    private function makeAthletes(string $sportKey, array $bp, int $count, $sport, array $clubs): array
    {
        // Split by gender, because the board prints the name next to a gendered
        // division ("Senior Women -61 kg") and a mismatch is the first thing a
        // person in the hall would notice.
        $first = [
            'Male' => ['Ahmed', 'Ali', 'Omar', 'Yusuf', 'Khalid', 'Hassan', 'Salman', 'Rashid', 'Jassim', 'Fahad'],
            'Female' => ['Fatima', 'Noora', 'Maryam', 'Sara', 'Aisha', 'Layla', 'Huda', 'Reem', 'Dana', 'Shaikha'],
        ];
        $last = ['Al Dosari', 'Al Khalifa', 'Abdulla', 'Al Mahmood', 'Hassan', 'Al Sayed', 'Janahi',
            'Al Alawi', 'Buhazza', 'Al Qassab', 'Kadhem', 'Al Binali'];
        $nations = ['BH', 'BH', 'BH', 'BH', 'AE', 'KW', 'QA', 'OM', 'JO', 'SA'];

        // Four senior classes per gender, taken from the sport's own table.
        // Four is deliberate: with 24 athletes it puts three in each division,
        // which draws a semi-final and a final rather than a lone bout.
        $divisions = $sport->weightDivisions();
        $bands = [];
        foreach (['Male' => 'male', 'Female' => 'female'] as $gender => $key) {
            $classes = collect($divisions['Senior'][$key] ?? [])
                // The open class (+X) has no ceiling to cut to, so athletes
                // aimed at it would land anywhere above it. Bounded classes only.
                ->filter(fn ($c) => ($c['max'] ?? 0) > 0 && ($c['max'] ?? 0) < 150)
                ->pluck('max')->values();

            // Spread the picks across the table rather than taking the lightest
            // four, so the entry list looks like a real one.
            $step = max(1, (int) floor($classes->count() / 4));
            $bands[$gender] = $classes->filter(fn ($v, $i) => $i % $step === 0)->take(4)->values()->all()
                ?: $classes->take(4)->all();
        }

        $athletes = [];

        for ($i = 0; $i < $count; $i++) {
            $gender = $i % 3 === 2 ? 'Female' : 'Male';
            $band = $bands[$gender][$i % count($bands[$gender])];
            $weight = round($band - random_int(5, 45) / 10, 1);   // just under the limit, as athletes cut to
            $age = random_int(19, 31);
            $height = (int) round(($gender === 'Male' ? 165 : 155) + ($weight - 45) * 0.55 + random_int(-4, 4));

            $name = $first[$gender][array_rand($first[$gender])].' '.$last[array_rand($last)];
            // Offset so club does not track the weight band. Both cycle on
            // four, so a plain $i % 4 puts every athlete in a division at the
            // same club — and the draw then pairs club-mates in every bout,
            // which is the one thing a real tournament seeds AGAINST.
            $club = $clubs[($i + intdiv($i, count($bands[$gender]))) % count($clubs)];

            $user = User::create([
                'full_name' => $name,
                'name' => $name,
                'email' => Str::slug($name).'-'.$sportKey.'-'.$i.self::EMAIL_DOMAIN,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'gender' => $gender,
                'birthdate' => now()->subYears($age)->subDays(random_int(0, 364))->toDateString(),
                'nationality' => $nations[array_rand($nations)],
                'height_cm' => $height,
                'is_discoverable' => false,
            ]);
            $this->m->track('users', $user->id);

            $this->makePhysiqueHistory($user, $weight, $height);
            // A MEMBERSHIP, not just an affiliation: the hall board reads the
            // competitor's club from memberClubs (it wants a crest and a
            // country, which free text cannot give it).
            DB::table('memberships')->insert([
                'user_id' => $user->id, 'tenant_id' => $club->id, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->m->track('memberships', DB::getPdo()->lastInsertId());

            $affiliation = $this->makeAffiliation($user, $club->club_name, $sportKey, $bp);
            [$colour, $grade] = $this->makeBeltLadder($user, $bp, $affiliation);

            $athletes[] = [
                'user' => $user,
                'club' => $club,
                'age' => $age,
                'weight' => $weight,
                'belt_colour' => $colour,
                'belt_grade' => $grade,
            ];
        }

        return $athletes;
    }

    /**
     * Dated weigh-ins over the past year.
     *
     * The most recent one is what Enrolment::declaredWeight() classifies on, so
     * the series has to END at the athlete's competition weight — a trend that
     * drifts away from it would put them in the wrong division.
     */
    private function makePhysiqueHistory(User $user, float $weight, int $height): void
    {
        $points = random_int(4, 7);

        for ($i = $points - 1; $i >= 0; $i--) {
            // Older records sit a little above the athlete's cut weight.
            $w = round($weight + ($i * random_int(3, 9) / 10), 1);
            $bmi = round($w / (($height / 100) ** 2), 1);

            $record = HealthRecord::create([
                'user_id' => $user->id,
                'recorded_at' => now()->subWeeks($i * 7)->subDays(random_int(0, 5)),
                'weight' => $w,
                'height' => $height,
                'bmi' => $bmi,
                'body_fat_percentage' => round(random_int(80, 175) / 10, 1),
                'muscle_mass' => round($w * random_int(40, 48) / 100, 1),
                'body_water_percentage' => round(random_int(550, 640) / 10, 1),
            ]);
            $this->m->track('health_records', $record->id);
        }
    }

    /** Where they train, and the skill that proves they practise this sport. */
    private function makeAffiliation(User $user, string $club, string $sportKey, array $bp): ClubAffiliation
    {
        $years = random_int(3, 12);

        $affiliation = ClubAffiliation::create([
            'member_id' => $user->id,
            'club_name' => $club,
            'start_date' => now()->subYears($years)->toDateString(),
            'location' => 'Bahrain',
            'description' => 'Demo affiliation seeded by demo:competition.',
        ]);
        $this->m->track('club_affiliations', $affiliation->id);

        $skill = SkillAcquisition::create([
            'user_id' => $user->id,
            'club_affiliation_id' => $affiliation->id,
            'skill_name' => ucfirst($sportKey),
            'activity_name' => ucfirst($sportKey),
            'start_date' => now()->subYears($years)->toDateString(),
            'duration_months' => $years * 12,
            // BeltRank's weakest source — a claim, not a grading. Seeded so the
            // fallback chain has something to fall back TO.
            'proficiency_level' => $bp['ladder'][min(count($bp['ladder']) - 1, intdiv($years, 2))][0].' Belt',
        ]);
        $this->m->track('skill_acquisitions', $skill->id);

        return $affiliation;
    }

    /**
     * The gradings behind their current belt.
     *
     * A ladder rather than one row, because that is what a real member's profile
     * looks like and because BeltRank must pick the MOST RECENT — seeding only
     * the current belt would never exercise that.
     *
     * @return array{0: string, 1: string} colour and grade of the newest grading
     */
    private function makeBeltLadder(User $user, array $bp, ClubAffiliation $affiliation): array
    {
        $steps = random_int(3, min(7, count($bp['ladder'])));
        $colour = 'White';
        $grade = '';

        for ($i = 0; $i < $steps; $i++) {
            [$colour, $grade] = $bp['ladder'][$i];

            $cert = MemberCertification::create([
                'user_id' => $user->id,
                'title' => trim($colour.' Belt '.$grade),
                'issuer' => $bp['issuer'],
                // Oldest first, so the newest grading is the last one written.
                'issue_date' => now()->subMonths(($steps - $i) * random_int(7, 13))->toDateString(),
                'credential_id' => strtoupper(Str::random(3)).'-'.random_int(10000, 99999),
                'notes' => 'Graded at '.$affiliation->club_name.'. Demo record.',
            ]);
            $this->m->track('member_certifications', $cert->id);
        }

        return [$colour, $grade];
    }

    /**
     * Remove exactly what was seeded, newest table first so foreign keys never
     * block a delete. Files are not tracked — this command uploads none.
     */
    private function purge(): int
    {
        $manifest = DemoManifest::load(self::MANIFEST);

        if (! $manifest) {
            $this->warn('No competition demo manifest found — nothing to remove.');

            return self::SUCCESS;
        }

        // Reverse dependency order: children before the rows they point at.
        $order = [
            'event_officials',
            'karate_court_displays', 'court_displays',
            'event_matches', 'club_event_registrations', 'event_categories', 'club_events',
            'member_certifications', 'skill_acquisitions', 'club_affiliations', 'health_records',
            'memberships', 'users', 'tenants',
        ];

        DB::transaction(function () use ($manifest, $order) {
            foreach ($order as $table) {
                $ids = $manifest['tables'][$table] ?? [];
                if (! $ids) {
                    continue;
                }
                // forceDelete semantics for soft-deleting models: a demo row must
                // leave completely, not linger as a tombstone.
                $deleted = DB::table($table)->whereIn('id', $ids)->delete();
                $this->line('   removed '.str_pad($table, 28).$deleted);
            }
        });

        DemoManifest::delete(self::MANIFEST);
        $this->info('✅ Competition demo removed.');

        return self::SUCCESS;
    }
}
