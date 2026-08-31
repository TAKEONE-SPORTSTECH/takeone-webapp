<?php

namespace Database\Seeders;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Clubs\Models\Tenant;
use App\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use App\Sports\Combat\Engine\Scheduler;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Five Taekwondo championships, each with a generated poster image, and one
 * deliberately left EMPTY so the hand-arrangement flow can be exercised from a
 * blank canvas (Arrangement::clear() / drag from the bench).
 *
 * Differs from TaekwondoChampionshipSeeder in three ways:
 *
 *  1. It is SELF-CONTAINED — it creates its own athlete pool. The stock seeder
 *     reads existing club memberships, which a freshly-migrated database does
 *     not have.
 *  2. Every event gets a poster written to storage/app/public/events/ and
 *     recorded in club_events.images.
 *  3. The Masters championship is seeded with divisions but ZERO entrants and
 *     no draw — enrolment is open, nobody has entered yet.
 *
 * The enrolment rule from the live register() flow is preserved: a member is
 * only entered into a division their own gender + age + weight classify into.
 *
 *   php artisan db:seed --class=TaekwondoShowcaseSeeder
 *
 * Idempotent: re-running resets each event's roster, draw and poster.
 */
class TaekwondoShowcaseSeeder extends Seeder
{
    /** "AgeGroup|gender" => int[] user ids */
    private array $buckets = [];

    private array $spectatorPool = [];

    public function run(): void
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            $this->command?->warn('No tenant — run DatabaseSeeder first.');

            return;
        }

        $owner = User::find($tenant->owner_user_id) ?? User::first();
        if (! $owner) {
            $this->command?->warn('No user to own the events.');

            return;
        }

        Storage::disk('public')->makeDirectory('events');

        // The organiser must be a member of their own club, or PersonalEventController
        // ::index() hides every event from them: visibility is own-club, or a scope
        // that reaches you (worldwide/inter_club, or nationwide/regional matching
        // your club's country). A non-member matches none of those.
        DB::table('memberships')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $owner->id],
            ['status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->buildAthletePool($tenant->id);

        foreach ($this->events() as $def) {
            $event = $this->upsertEvent($tenant->id, $owner->id, $def);
            $this->resetEvent($event);

            $cats = $this->makeDivisions($event, $def['divisions']);

            if (empty($def['empty'])) {
                $this->enroll($event, $cats, $def);
                $this->buildDraws($event);
            }

            $this->command?->info(sprintf(
                '  %s %-46s %d divisions · %d competitors · %d spectators',
                empty($def['empty']) ? '✓' : '○',
                $event->title,
                count($cats),
                $event->registrations()->where('role', 'participant')->count(),
                $event->registrations()->where('role', 'spectator')->count(),
            ));
        }

        $this->command?->info('Done. The ○ event is intentionally empty.');
    }

    /* ---------------- athlete pool ---------------- */

    /**
     * Create (once) enough classifiable members to fill the four populated
     * championships, and register them to the club. Existing members with a
     * gender + birthdate are picked up too, so this composes with other seeders.
     */
    private function buildAthletePool(int $tenantId): void
    {
        // How many athletes each age group needs, per gender: 4 divisions at
        // perDiv entrants, plus headroom so a division is never short.
        $need = ['Senior' => 36, 'Cadet' => 28, 'Junior' => 28, 'Kids' => 28];

        // ISO 3166-1 alpha-2, matching tenants.country and what flag-icons needs
        // (the board renders `fi fi-{code}`). Alpha-3 would mis-flag: UAE -> "ua"
        // is Ukraine, KSA -> "ks" is nothing.
        $countries = ['BH', 'SA', 'AE', 'KW', 'QA', 'OM', 'JO', 'EG'];
        // 18 x 20 = 360 combinations per gender, walked so that the first name
        // only repeats after every surname has been used. 240 athletes therefore
        // all get distinct names — with a 12 x 10 pool the repeats were
        // unavoidable, and a people-picker full of identical rows is unusable.
        $male = ['Ali', 'Omar', 'Yusuf', 'Hamad', 'Khalid', 'Salman', 'Rashid', 'Faisal', 'Tariq',
            'Nasser', 'Jassim', 'Mahmood', 'Ahmed', 'Ibrahim', 'Saeed', 'Younis', 'Bader', 'Fahad'];
        $female = ['Noor', 'Layla', 'Fatima', 'Mariam', 'Sara', 'Hessa', 'Aisha', 'Zahra', 'Reem',
            'Dana', 'Huda', 'Amal', 'Shaikha', 'Munira', 'Latifa', 'Wadha', 'Ghada', 'Rawan'];
        $family = ['Al Khalifa', 'Al Dosari', 'Al Mannai', 'Hassan', 'Al Sayed', 'Janahi',
            'Al Ansari', 'Bucheeri', 'Al Awadhi', 'Radhi', 'Al Zayani', 'Kanoo', 'Fakhro',
            'Al Binali', 'Shamlan', 'Al Rumaihi', 'Buheji', 'Al Qassab', 'Almoayed', 'Sharif'];

        // One running index per gender across every age group, so a name is never
        // reused between Kids and Senior.
        $nameSeq = ['male' => 0, 'female' => 0];

        // Repair athletes seeded by an earlier version of this file, which wrote
        // ISO alpha-3. The pool loop below is skipped once the pool is full, so
        // it can never correct them — this has to happen up front.
        $alpha3 = ['BHR' => 'BH', 'KSA' => 'SA', 'UAE' => 'AE', 'KWT' => 'KW',
            'QAT' => 'QA', 'OMN' => 'OM', 'JOR' => 'JO', 'EGY' => 'EG'];

        foreach ($alpha3 as $from => $to) {
            User::where('email', 'like', 'tkd.%@takeone.test')
                ->where('nationality', $from)
                ->update(['nationality' => $to]);
        }

        // Pick up anyone already usable.
        foreach (User::whereNotNull('gender')->whereNotNull('birthdate')->get(['id', 'gender', 'birthdate']) as $u) {
            $group = $this->ageGroup(Carbon::parse($u->birthdate)->age);
            $this->spectatorPool[] = $u->id;
            if ($group) {
                $this->buckets[$group.'|'.strtolower($u->gender)][] = $u->id;
            }
        }

        $created = 0;

        foreach ($need as $group => $perGender) {
            foreach (['male', 'female'] as $gender) {
                $key = $group.'|'.$gender;

                // Always walk the full range rather than starting where the pool
                // already reaches: updateOrCreate then repairs athletes this
                // seeder created earlier (names, country codes) instead of
                // silently skipping them. Non-seeded members keep their bucket
                // place either way.
                for ($i = 0; $i < $perGender; $i++) {
                    $pool  = $gender === 'male' ? $male : $female;
                    $seq   = $nameSeq[$gender]++;
                    // Surname advances every step, first name only after a full
                    // lap of surnames — so the pair is unique for 360 athletes.
                    $first = $pool[intdiv($seq, count($family)) % count($pool)];
                    $last  = $family[$seq % count($family)];
                    $email = sprintf('tkd.%s.%s.%d@takeone.test', strtolower($group), $gender, $i);

                    // updateOrCreate, not firstOrCreate: re-running must be able to
                    // correct seeded athletes in place (e.g. the alpha-3 country
                    // codes an earlier version of this seeder wrote).
                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $first,
                            'full_name' => $first.' '.$last,
                            'password' => Hash::make('password'),
                            'gender' => $gender,
                            'birthdate' => $this->birthdateFor($group),
                            'nationality' => $countries[$i % count($countries)],
                            // Real-shaped Bahraini mobile, unique per athlete: the
                            // column carries a unique index, and a people-picker
                            // needs a second identifier to tell two members apart.
                            'mobile' => ['code' => '+973', 'number' => (string) (33000000 + ($gender === 'male' ? 0 : 400000) + $seq)],
                            'email_verified_at' => now(),
                        ]
                    );

                    if ($user->wasRecentlyCreated) {
                        $created++;
                    }

                    DB::table('memberships')->updateOrInsert(
                        ['tenant_id' => $tenantId, 'user_id' => $user->id],
                        ['status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                    );

                    if (! in_array($user->id, $this->buckets[$key] ?? [], true)) {
                        $this->buckets[$key][] = $user->id;
                    }
                    $this->spectatorPool[] = $user->id;
                }
            }
        }

        $this->command?->info("Athlete pool: {$created} created · ".collect($this->buckets)
            ->map(fn ($v, $k) => $k.'='.count($v))->implode(' '));
    }

    /** A birthdate that lands squarely inside the age group (never on a boundary). */
    private function birthdateFor(string $group): string
    {
        $age = match ($group) {
            'Kids' => rand(7, 10),
            'Cadet' => 13,
            'Junior' => 16,
            'Senior' => rand(19, 29),
            'Masters' => rand(32, 45),
        };

        return now()->subYears($age)->subDays(rand(10, 300))->toDateString();
    }

    private function ageGroup(int $age): ?string
    {
        return match (true) {
            $age >= 6 && $age <= 11 => 'Kids',
            $age >= 12 && $age <= 14 => 'Cadet',
            $age >= 15 && $age <= 17 => 'Junior',
            $age >= 18 && $age <= 30 => 'Senior',
            $age >= 31 => 'Masters',
            default => null,
        };
    }

    /* ---------------- event + divisions ---------------- */

    private function upsertEvent(int $tenantId, int $ownerId, array $d): ClubEvent
    {
        return ClubEvent::updateOrCreate(
            ['tenant_id' => $tenantId, 'title' => $d['title']],
            [
                'created_by' => $ownerId,
                'event_type' => 'championship',
                'sport' => 'taekwondo',
                'scope' => $d['scope'],
                'icon' => 'bi-trophy-fill',
                'color' => $d['color'],
                'description' => $d['about'],
                'images' => [$this->poster($d)],
                'date' => now()->addDays($d['in'])->toDateString(),
                'end_date' => isset($d['endIn']) ? now()->addDays($d['endIn'])->toDateString() : null,
                'start_time' => $d['start'],
                'end_time' => $d['end'],
                'location' => $d['location'],
                'level' => $d['level'],
                'courts' => $d['courts'] ?? null,
                'minutes_per_match' => 8,
                'weigh_in_at' => now()->addDays($d['in'] - 1)->setTime(8, 0)->toDateTimeString(),
                'enrollment_starts_at' => now()->subDays(3)->toDateString(),
                'enrollment_ends_at' => now()->addDays(max(1, $d['in'] - 2))->toDateString(),
                'participant_fee' => $d['fee'],
                'spectator_enabled' => ! empty($d['spectator']),
                'spectator_fee' => $d['spectator'] ?? null,
                'prize' => $d['prize'] ?? null,
                'requirements' => $d['requirements'] ?? null,
                'tags' => $d['tags'] ?? ['Taekwondo'],
                'status' => 'active',
                'is_archived' => false,
            ]
        );
    }

    /** Wipe roster + draw so re-running is idempotent. */
    private function resetEvent(ClubEvent $event): void
    {
        EventMatch::where('event_id', $event->id)->delete();
        ClubEventRegistration::where('event_id', $event->id)->delete();
        $event->categories()->delete();
    }

    /** @return EventCategory[] keyed by "AgeGroup|gender|label" */
    private function makeDivisions(ClubEvent $event, array $divisions): array
    {
        $cats = [];
        $sort = 0;

        foreach ($divisions as [$age, $gender, $labels]) {
            foreach ($labels as $label) {
                $name = $age.' '.($gender === 'female' ? 'Women' : 'Men').' '.$label.' kg';
                $cats[$age.'|'.$gender.'|'.$label] = EventCategory::create([
                    'event_id' => $event->id,
                    'name' => $name,
                    'weight_class' => $label.' kg',
                    'capacity' => 16,
                    'status' => 'enrolling',
                    'sort_order' => ++$sort,
                ]);
            }
        }

        return $cats;
    }

    /* ---------------- enrolment (same rule as register()) ---------------- */

    private function enroll(ClubEvent $event, array $cats, array $def): void
    {
        $used = [];
        $perDiv = $def['perDiv'] ?? 7;
        $paidFee = $event->participant_fee && ! str_contains(strtolower($event->participant_fee), 'free');

        foreach ($def['divisions'] as [$age, $gender, $labels]) {
            foreach ($labels as $label) {
                $cat = $cats[$age.'|'.$gender.'|'.$label];
                $pool = $this->buckets[$age.'|'.$gender] ?? [];
                if (! $pool) {
                    continue;
                }

                $weight = $this->weightForClass($age, $gender, $label);
                if ($weight === null) {
                    continue;
                }

                $taken = 0;
                foreach ($pool as $uid) {
                    if ($taken >= $perDiv) {
                        break;
                    }
                    // One division per athlete per event (they may compete in others).
                    if (isset($used[$uid])) {
                        continue;
                    }

                    // Belt-and-braces: the athlete's own age+gender+weight must
                    // classify into THIS division, exactly as register() requires.
                    $u = User::find($uid);
                    $cls = $u ? classifyTaekwondo($u->gender, Carbon::parse($u->birthdate)->age, $weight) : null;
                    $expected = $cls
                        ? $age.' '.($gender === 'female' ? 'Women' : 'Men').' '.$cls['category'].' kg'
                        : null;

                    if ($expected !== $cat->name) {
                        continue;
                    }

                    $paid = ! $paidFee || rand(0, 4) !== 0;   // ~80% settled
                    // Of those settled, ~75% have been signed off by an official.
                    // The rest sit amber on the roster: money is claimed but
                    // nobody has put their name to it yet — the state the
                    // organiser is looking for before the draw.
                    $verified = $paid && rand(0, 3) !== 0;
                    $weighedAt = $paid ? now()->subDays(rand(0, 2)) : null;

                    ClubEventRegistration::create([
                        'event_id' => $event->id,
                        'user_id' => $uid,
                        'role' => 'participant',
                        'status' => 'joined',
                        'paid' => $paid,
                        'paid_at' => $paid ? now()->subDays(rand(0, 6)) : null,
                        'paid_by' => $verified ? $event->created_by : null,
                        'category_id' => $cat->id,
                        'weight' => $weight,
                        'meta' => $u->nationality,
                        'weighed_in_at' => $weighedAt,
                        'weighed_in_by' => ($weighedAt && $verified) ? $event->created_by : null,
                        'registered_at' => now()->subDays(rand(0, 8)),
                    ]);

                    $used[$uid] = true;
                    $taken++;
                }
            }
        }

        if ($event->spectator_enabled) {
            $want = $def['spectators'] ?? 12;
            $added = 0;

            foreach (collect($this->spectatorPool)->unique()->shuffle() as $uid) {
                if ($added >= $want) {
                    break;
                }
                if (isset($used[$uid])) {
                    continue;
                }

                ClubEventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $uid,
                    'role' => 'spectator',
                    'status' => 'joined',
                    'paid' => true,
                    'registered_at' => now()->subDays(rand(0, 5)),
                ]);

                $used[$uid] = true;
                $added++;
            }
        }
    }

    /** A weight (kg) guaranteed to classify into the given division label. */
    private function weightForClass(string $age, string $gender, string $label): ?float
    {
        $classes = config('taekwondo_divisions')[$age][$gender] ?? null;
        if (! $classes) {
            return null;
        }

        $prevMax = 0;

        foreach ($classes as $c) {
            if ($c['label'] === $label) {
                if (str_starts_with($label, '+')) {
                    return (float) ($c['min'] + 3);
                }
                $lower = max($prevMax, (float) $c['min']);
                $upper = (float) $c['max'];
                $w = round(($lower + $upper) / 2, 1);

                return $w > $lower ? $w : $upper;
            }
            $prevMax = (float) $c['max'];
        }

        return null;
    }

    private function buildDraws(ClubEvent $event): void
    {
        $draws = app(DrawEngine::class);
        $scheduler = app(Scheduler::class);
        $event->refresh();

        foreach ($event->categories()->get() as $cat) {
            if ($cat->registrations()->where('role', 'participant')->count() >= 2) {
                $draws->build($event, $cat, paidOnly: false);   // provisional, pre-start
            }
        }

        $scheduler->scheduleAndNumber($event);
    }

    /* ---------------- poster ---------------- */

    /**
     * Draw a poster for the event and return its storage-relative path (which is
     * what club_events.images holds — the views prefix it with asset('storage/')).
     */
    private function poster(array $d): string
    {
        $path = 'events/'.$d['slug'].'.jpg';
        [$w, $h] = [1200, 675];

        $img = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = $this->rgb($d['color']);

        // Vertical gradient from the event colour into near-black.
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate(
                $img,
                (int) ($r * (1 - $t) + 12 * $t),
                (int) ($g * (1 - $t) + 12 * $t),
                (int) ($b * (1 - $t) + 20 * $t),
            );
            imageline($img, 0, $y, $w, $y, $c);
        }

        // Soft diagonal banding for a bit of texture.
        $veil = imagecolorallocatealpha($img, 255, 255, 255, 118);
        for ($i = -$h; $i < $w; $i += 90) {
            imagefilledpolygon($img, [$i, $h, $i + 34, $h, $i + 34 + $h, 0, $i + $h, 0], $veil);
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $muted = imagecolorallocatealpha($img, 255, 255, 255, 45);
        $bold = 'C:/Windows/Fonts/arialbd.ttf';
        $reg = 'C:/Windows/Fonts/arial.ttf';

        // Kicker
        imagettftext($img, 22, 0, 72, 120, $muted, $bold, mb_strtoupper($d['kicker']));

        // Title, wrapped to the poster width.
        $y = 210;
        foreach ($this->wrap($d['title'], 21) as $line) {
            imagettftext($img, 58, 0, 70, $y, $white, $bold, $line);
            $y += 78;
        }

        // Rule + footer details
        imagefilledrectangle($img, 72, $y + 6, 210, $y + 11, $white);
        imagettftext($img, 24, 0, 72, $y + 76, $muted, $reg, $d['location']);
        imagettftext($img, 24, 0, 72, $y + 122, $muted, $reg, $d['level']);

        ob_start();
        imagejpeg($img, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** @return string[] */
    private function wrap(string $text, int $perLine): array
    {
        return explode("\n", wordwrap($text, $perLine, "\n", false));
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /* ---------------- catalog ---------------- */

    private function events(): array
    {
        return [
            [
                'title' => 'Bahrain National Taekwondo Championship 2026',
                'slug' => 'bahrain-national-2026',
                'kicker' => 'National Championship',
                'color' => '#6d28d9',
                'scope' => 'nationwide', 'in' => 21, 'endIn' => 23,
                'start' => '09:00', 'end' => '20:00', 'courts' => 3,
                'location' => 'Khalifa Sports City · Isa Town', 'level' => 'National · Senior',
                'fee' => 'BHD 25', 'spectator' => 'BHD 10',
                'prize' => 'Gold/Silver/Bronze + National ranking',
                'about' => 'The national senior championship — Olympic-style sparring across the WT senior weight divisions for men and women. Single-elimination with repechage bronze.',
                'tags' => ['Taekwondo', 'Senior', 'National', 'Ticketed'],
                'requirements' => ['Valid national federation license', 'Make weight at the official weigh-in', 'WT-approved protective gear'],
                'perDiv' => 8, 'spectators' => 16,
                'divisions' => [
                    ['Senior', 'male', ['-58', '-68', '-74', '-80']],
                    ['Senior', 'female', ['-49', '-57', '-62', '-67']],
                ],
            ],
            [
                'title' => 'Gulf Cadet Open 2026',
                'slug' => 'gulf-cadet-open-2026',
                'kicker' => 'Regional Open',
                'color' => '#0e7490',
                'scope' => 'regional', 'in' => 35, 'endIn' => 36,
                'start' => '10:00', 'end' => '18:00', 'courts' => 2,
                'location' => 'Gulf Arena · Manama', 'level' => 'Regional · Cadet (12–14)',
                'fee' => 'BHD 15', 'spectator' => 'BHD 5',
                'prize' => 'Medals + Gulf ranking points',
                'about' => 'A regional cadet championship for 12–14 year-olds. Lighter cadet weight divisions, shorter rounds, full protective gear required.',
                'tags' => ['Taekwondo', 'Cadet', 'Regional'],
                'requirements' => ['Cadet age (12–14) on competition day', 'Parental consent form', 'Full protective gear + head guard'],
                'perDiv' => 6, 'spectators' => 14,
                'divisions' => [
                    ['Cadet', 'male', ['-45', '-49', '-53', '-57']],
                    ['Cadet', 'female', ['-44', '-47', '-51', '-55']],
                ],
            ],
            [
                'title' => 'Junior Kyorugi Cup',
                'slug' => 'junior-kyorugi-cup',
                'kicker' => 'Club Cup',
                'color' => '#b45309',
                'scope' => 'internal', 'in' => 14,
                'start' => '14:00', 'end' => '20:00', 'courts' => 2,
                'location' => 'TAKEONE Dojang · Riffa', 'level' => 'Club · Junior (15–17)',
                'fee' => 'BHD 10', 'spectator' => 'Free',
                'prize' => 'Club medals + trophy',
                'about' => 'An internal club cup for juniors (15–17). One competition day, junior weight divisions, friends & family welcome to watch free.',
                'tags' => ['Taekwondo', 'Junior', 'Club'],
                'requirements' => ['Active club membership', 'Junior age (15–17)'],
                'perDiv' => 6, 'spectators' => 12,
                'divisions' => [
                    ['Junior', 'male', ['-55', '-59', '-63', '-68']],
                    ['Junior', 'female', ['-52', '-55', '-59', '-63']],
                ],
            ],
            [
                'title' => 'Little Tigers Kids Festival',
                'slug' => 'little-tigers-festival',
                'kicker' => 'Kids Festival',
                'color' => '#be185d',
                'scope' => 'internal', 'in' => 10,
                'start' => '09:30', 'end' => '14:00', 'courts' => 2,
                'location' => 'TAKEONE Dojang · Riffa', 'level' => 'Club · Kids (6–11)',
                'fee' => 'Free', 'spectator' => 'Free',
                'prize' => 'Participation medals for all',
                'about' => 'A friendly kids festival (ages 6–11). Light contact, lots of encouragement, a medal for every little tiger. Free to enter and to watch.',
                'tags' => ['Taekwondo', 'Kids', 'Festival'],
                'requirements' => ['Kids age (6–11)', 'Parent present on the day'],
                'perDiv' => 6, 'spectators' => 18,
                'divisions' => [
                    ['Kids', 'male', ['-30', '-33', '-36', '-40']],
                    ['Kids', 'female', ['-30', '-33', '-36', '-40']],
                ],
            ],

            // ── Intentionally empty ────────────────────────────────────────────
            // Divisions exist and enrolment is open, but nobody has entered and
            // no draw has been cut. This is the blank canvas for hand-arranging
            // a bracket (Arrangement::clear() / drag from the bench).
            [
                'title' => 'Masters Veterans Championship',
                'slug' => 'masters-veterans',
                'kicker' => 'Enrolment Open',
                'color' => '#334155',
                'empty' => true,
                'scope' => 'nationwide', 'in' => 28, 'endIn' => 29,
                'start' => '11:00', 'end' => '19:00', 'courts' => 2,
                'location' => 'National Arena · Manama', 'level' => 'National · Masters (31+)',
                'fee' => 'BHD 20', 'spectator' => 'BHD 5',
                'prize' => 'Veteran medals + recognition',
                'about' => 'A masters/veterans championship for athletes 31 and older. Broad weight bands, adjusted rounds, celebrating lifelong taekwondo. Enrolment has just opened — no entries yet.',
                'tags' => ['Taekwondo', 'Masters', 'Veterans', 'National'],
                'requirements' => ['Age 31+ on competition day', 'Medical clearance', 'WT-approved gear'],
                'divisions' => [
                    ['Masters', 'male', ['-60', '-70', '-80', '+80']],
                    ['Masters', 'female', ['-55', '-63', '-72', '+72']],
                ],
            ],
        ];
    }
}
