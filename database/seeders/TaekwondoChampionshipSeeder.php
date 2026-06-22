<?php

namespace Database\Seeders;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\Tenant;
use App\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use App\Sports\Combat\Engine\Scheduler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds several Taekwondo championships that STRICTLY follow the creator's rules:
 *
 *  - Each event defines its own weight divisions (age group × gender × weight class).
 *  - A member is only enrolled as a COMPETITOR into a division their own
 *    gender + age (from birthdate) + weight classify into — exactly what the live
 *    register() flow enforces. Nobody is placed in a category they don't fit.
 *  - Members who can't be placed are added as SPECTATORS instead.
 *
 * It also repairs the previously mis-seeded "Bahrain National" event by rebuilding
 * its roster under these rules.
 *
 *   php artisan db:seed --class=TaekwondoChampionshipSeeder
 */
class TaekwondoChampionshipSeeder extends Seeder
{
    private array $buckets = [];   // "AgeGroup|gender" => array<int userId>  (real, classifiable members)
    private array $spectatorPool = [];

    public function run(): void
    {
        $tenant = Tenant::where('club_name', 'EMPEROR TAEKWONDO ACADEMY')->first() ?? Tenant::first();
        if (! $tenant) { $this->command?->warn('No tenant.'); return; }

        $owner = User::where('email', 'emperorsameta@gmail.com')->first()
            ?? User::find(DB::table('memberships')->where('tenant_id', $tenant->id)->value('user_id'));
        if (! $owner) { $this->command?->warn('No owner.'); return; }

        $this->buildMemberBuckets($tenant->id);
        $this->command?->info("Buckets: " . collect($this->buckets)->map(fn ($v, $k) => "$k=" . count($v))->implode(' '));

        foreach ($this->events() as $def) {
            $event = $this->upsertEvent($tenant->id, $owner->id, $def);
            $this->resetEvent($event);
            $cats = $this->makeDivisions($event, $def['divisions']);
            $this->enroll($event, $cats, $def);
            $this->buildDraws($event);
            $this->command?->info(sprintf(
                "  ✓ %-42s  %d divisions · %d competitors · %d spectators",
                $event->title, count($cats),
                $event->participantRegistrations()->count(),
                $event->registrations()->where('role', 'spectator')->count(),
            ));
        }

        $this->command?->info('Done.');
    }

    /* ---------------- member pool ---------------- */

    private function buildMemberBuckets(int $tenantId): void
    {
        $ids = DB::table('memberships')->where('tenant_id', $tenantId)->distinct()->pluck('user_id');
        $users = User::whereIn('id', $ids)->whereNotNull('gender')->whereNotNull('birthdate')
            ->get(['id', 'gender', 'birthdate'])->shuffle();

        foreach ($users as $u) {
            $age   = \Carbon\Carbon::parse($u->birthdate)->age;
            $group = $this->ageGroup($age);
            if (! $group) { $this->spectatorPool[] = $u->id; continue; }
            $this->buckets[$group . '|' . strtolower($u->gender)][] = $u->id;
            $this->spectatorPool[] = $u->id; // also eligible to spectate elsewhere
        }
    }

    private function ageGroup(int $age): ?string
    {
        return match (true) {
            $age >= 6  && $age <= 11 => 'Kids',
            $age >= 12 && $age <= 14 => 'Cadet',
            $age >= 15 && $age <= 17 => 'Junior',
            $age >= 18 && $age <= 30 => 'Senior',
            $age >= 31               => 'Masters',
            default                  => null,
        };
    }

    /* ---------------- event + divisions ---------------- */

    private function upsertEvent(int $tenantId, int $ownerId, array $d): ClubEvent
    {
        return ClubEvent::updateOrCreate(
            ['tenant_id' => $tenantId, 'title' => $d['title']],
            [
                'created_by'           => $ownerId,
                'event_type'           => 'championship',
                'sport'                => 'taekwondo',
                'scope'                => $d['scope'],
                'icon'                 => 'bi-trophy-fill',
                'color'                => $d['color'] ?? '#6d28d9',
                'description'          => $d['about'],
                'date'                 => now()->addDays($d['in'])->toDateString(),
                'end_date'             => isset($d['endIn']) ? now()->addDays($d['endIn'])->toDateString() : null,
                'start_time'           => $d['start'],
                'end_time'             => $d['end'],
                'location'             => $d['location'],
                'level'                => $d['level'],
                'courts'               => $d['courts'] ?? null,
                'minutes_per_match'    => 8,
                'weigh_in_at'          => now()->addDays($d['in'] - 1)->setTime(8, 0)->toDateTimeString(),
                'enrollment_starts_at' => now()->subDays(3)->toDateString(),
                'enrollment_ends_at'   => now()->addDays(max(1, $d['in'] - 2))->toDateString(),
                'participant_fee'      => $d['fee'],
                'spectator_enabled'    => ! empty($d['spectator']),
                'spectator_fee'        => $d['spectator'] ?? null,
                'prize'                => $d['prize'] ?? null,
                'requirements'         => $d['requirements'] ?? null,
                'tags'                 => $d['tags'] ?? ['Taekwondo'],
                'status'               => 'active',
                'is_archived'          => false,
            ]
        );
    }

    /** Wipe a clean slate so re-running is idempotent and rules-compliant. */
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
                $name = $age . ' ' . ($gender === 'female' ? 'Women' : 'Men') . ' ' . $label . ' kg';
                $cats[$age . '|' . $gender . '|' . $label] = EventCategory::create([
                    'event_id'     => $event->id,
                    'name'         => $name,
                    'weight_class' => $label . ' kg',
                    'capacity'     => 16,
                    'status'       => 'enrolling',
                    'sort_order'   => ++$sort,
                ]);
            }
        }
        return $cats;
    }

    /* ---------------- enrolment (RULES ENFORCED) ---------------- */

    private function enroll(ClubEvent $event, array $cats, array $def): void
    {
        $used    = [];
        $perDiv  = $def['perDiv'] ?? 7;
        $paidFee = $event->participant_fee && ! str_contains(strtolower($event->participant_fee), 'free');

        foreach ($def['divisions'] as [$age, $gender, $labels]) {
            foreach ($labels as $label) {
                $cat    = $cats[$age . '|' . $gender . '|' . $label];
                $bucket = &$this->buckets[$age . '|' . $gender];
                if (empty($bucket)) { continue; }

                $taken = 0;
                foreach ($bucket as $k => $uid) {
                    if ($taken >= $perDiv) { break; }
                    if (isset($used[$uid])) { unset($bucket[$k]); continue; }

                    $weight = $this->weightForClass($age, $gender, $label);
                    if ($weight === null) { continue; }

                    // Guarantee the rule holds: the member's own age+gender+weight must
                    // classify into THIS division. (Belt-and-braces — should always pass.)
                    $u = User::find($uid);
                    $cls = $u ? classifyTaekwondo($u->gender, \Carbon\Carbon::parse($u->birthdate)->age, $weight) : null;
                    $expected = $cls ? ($age . ' ' . ($gender === 'female' ? 'Women' : 'Men') . ' ' . $cls['category'] . ' kg') : null;
                    if ($expected !== $cat->name) { continue; }

                    $paid = ! $paidFee || rand(0, 4) !== 0;   // ~80% settled, rest pending payment
                    ClubEventRegistration::create([
                        'event_id'      => $event->id,
                        'user_id'       => $uid,
                        'role'          => 'participant',
                        'status'        => 'joined',
                        'paid'          => $paid,
                        'category_id'   => $cat->id,
                        'weight'        => $weight,
                        'weighed_in_at' => $paid ? now()->subDays(rand(0, 2)) : null,
                        'registered_at' => now()->subDays(rand(0, 8)),
                    ]);
                    $used[$uid] = true;
                    unset($bucket[$k]);
                    $taken++;
                }
            }
        }

        // Spectators — anyone (no weight-class restriction), not already competing here.
        if ($event->spectator_enabled) {
            $want = $def['spectators'] ?? 12;
            $added = 0;
            foreach (collect($this->spectatorPool)->shuffle() as $uid) {
                if ($added >= $want) { break; }
                if (isset($used[$uid])) { continue; }
                ClubEventRegistration::create([
                    'event_id'      => $event->id,
                    'user_id'       => $uid,
                    'role'          => 'spectator',
                    'status'        => 'joined',
                    'paid'          => true,
                    'registered_at' => now()->subDays(rand(0, 5)),
                ]);
                $used[$uid] = true;
                $added++;
            }
        }
    }

    /** A weight (kg) that is guaranteed to classify into the given division label. */
    private function weightForClass(string $age, string $gender, string $label): ?float
    {
        $classes = config('taekwondo_divisions')[$age][$gender] ?? null;
        if (! $classes) { return null; }

        $prevMax = 0;
        foreach ($classes as $c) {
            if ($c['label'] === $label) {
                if (str_starts_with($label, '+')) {
                    return (float) ($c['min'] + 3);                 // just over the cap
                }
                $lower = max($prevMax, (float) $c['min']);
                $upper = (float) $c['max'];
                $w = round(($lower + $upper) / 2, 1);               // midpoint of (prev cap, cap]
                return $w > $lower ? $w : $upper;
            }
            $prevMax = (float) $c['max'];
        }
        return null;
    }

    private function buildDraws(ClubEvent $event): void
    {
        $draws     = app(DrawEngine::class);
        $scheduler = app(Scheduler::class);
        $event->refresh();

        foreach ($event->categories()->get() as $cat) {
            if ($cat->registrations()->where('role', 'participant')->count() >= 2) {
                $draws->build($event, $cat, paidOnly: false);   // provisional (pre-start) draw
            }
        }
        $scheduler->scheduleAndNumber($event);
    }

    /* ---------------- catalog: DIFFERENT rule sets ---------------- */

    private function events(): array
    {
        return [
            [
                'title' => 'Bahrain National Taekwondo Championship 2026',
                'scope' => 'nationwide', 'in' => 21, 'endIn' => 23,
                'start' => '09:00', 'end' => '20:00', 'courts' => 3,
                'location' => 'Khalifa Sports City · Isa Town', 'level' => 'National',
                'fee' => 'BHD 25', 'spectator' => 'BHD 10', 'prize' => 'Gold/Silver/Bronze + National ranking',
                'about' => 'The national senior championship — Olympic-style sparring across the WT senior weight divisions for men and women. Single-elimination with repechage bronze.',
                'tags' => ['Taekwondo', 'Senior', 'National', 'Ticketed'],
                'requirements' => ['Valid national federation license', 'Make weight at the official weigh-in', 'WT-approved protective gear'],
                'perDiv' => 8, 'spectators' => 16,
                'divisions' => [
                    ['Senior', 'male',   ['-58', '-68', '-74', '-80']],
                    ['Senior', 'female', ['-49', '-57', '-62', '-67']],
                ],
            ],
            [
                'title' => 'Gulf Cadet Open 2026',
                'scope' => 'regional', 'in' => 35, 'endIn' => 36,
                'start' => '10:00', 'end' => '18:00', 'courts' => 2,
                'location' => 'Gulf Arena · Manama', 'level' => 'Regional · Cadet (12–14)',
                'fee' => 'BHD 15', 'spectator' => 'BHD 5', 'prize' => 'Medals + Gulf ranking points',
                'about' => 'A regional cadet championship for 12–14 year-olds. Lighter cadet weight divisions, shorter rounds, full protective gear required.',
                'tags' => ['Taekwondo', 'Cadet', 'Regional'],
                'requirements' => ['Cadet age (12–14) on competition day', 'Parental consent form', 'Full protective gear + head guard'],
                'perDiv' => 6, 'spectators' => 14,
                'divisions' => [
                    ['Cadet', 'male',   ['-45', '-49', '-53', '-57']],
                    ['Cadet', 'female', ['-44', '-47', '-51', '-55']],
                ],
            ],
            [
                'title' => 'Junior Kyorugi Cup',
                'scope' => 'internal', 'in' => 14,
                'start' => '14:00', 'end' => '20:00', 'courts' => 2,
                'location' => 'Emperor Dojang · Riffa', 'level' => 'Club · Junior (15–17)',
                'fee' => 'BHD 10', 'spectator' => 'Free', 'prize' => 'Club medals + trophy',
                'about' => 'An internal club cup for juniors (15–17). One competition day, junior weight divisions, friends & family welcome to watch free.',
                'tags' => ['Taekwondo', 'Junior', 'Club'],
                'requirements' => ['Active club membership', 'Junior age (15–17)'],
                'perDiv' => 6, 'spectators' => 12,
                'divisions' => [
                    ['Junior', 'male',   ['-55', '-59', '-63', '-68']],
                    ['Junior', 'female', ['-52', '-55', '-59', '-63']],
                ],
            ],
            [
                'title' => 'Little Tigers Kids Festival',
                'scope' => 'internal', 'in' => 10,
                'start' => '09:30', 'end' => '14:00', 'courts' => 2,
                'location' => 'Emperor Dojang · Riffa', 'level' => 'Club · Kids (6–11)',
                'fee' => 'Free', 'spectator' => 'Free', 'prize' => 'Participation medals for all',
                'about' => 'A friendly kids festival (ages 6–11). Light contact, lots of encouragement, a medal for every little tiger. Free to enter and to watch.',
                'tags' => ['Taekwondo', 'Kids', 'Festival'],
                'requirements' => ['Kids age (6–11)', 'Parent present on the day'],
                'perDiv' => 6, 'spectators' => 18,
                'divisions' => [
                    ['Kids', 'male',   ['-30', '-33', '-36', '-40']],
                    ['Kids', 'female', ['-30', '-33', '-36', '-40']],
                ],
            ],
            [
                'title' => 'Masters Veterans Championship',
                'scope' => 'nationwide', 'in' => 28, 'endIn' => 29,
                'start' => '11:00', 'end' => '19:00', 'courts' => 2,
                'location' => 'National Arena · Manama', 'level' => 'National · Masters (31+)',
                'fee' => 'BHD 20', 'spectator' => 'BHD 5', 'prize' => 'Veteran medals + recognition',
                'about' => 'A masters/veterans championship for athletes 31 and older. Broad weight bands, adjusted rounds, celebrating lifelong taekwondo.',
                'tags' => ['Taekwondo', 'Masters', 'Veterans', 'National'],
                'requirements' => ['Age 31+ on competition day', 'Medical clearance', 'WT-approved gear'],
                'perDiv' => 6, 'spectators' => 12,
                'divisions' => [
                    ['Masters', 'male',   ['-60', '-70', '-80', '+80']],
                    ['Masters', 'female', ['-55', '-63', '-72', '+72']],
                ],
            ],
        ];
    }
}
