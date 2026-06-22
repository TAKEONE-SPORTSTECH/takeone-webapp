<?php

namespace Database\Seeders;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventShowcaseSeeder extends Seeder
{
    /**
     * Create the mobile Events showcase as REAL rows for one hero's club:
     * seven events spanning every type (class, race, belt test, tournament,
     * championship, world championship), with fees, spectator tickets, a full
     * participant/spectator crowd, and a Taekwondo championship with live,
     * completed and enrolling weight-category brackets.
     *
     * `HERO_EMAIL=you@example.com php artisan db:seed --class=EventShowcaseSeeder`
     */
    public function run(): void
    {
        $email = env('HERO_EMAIL', 'superadmin@takeone.bh');
        $hero  = User::where('email', $email)->first()
            ?? User::find(DB::table('memberships')->value('user_id'));
        if (! $hero) { $this->command?->warn('No hero/users.'); return; }

        $club = $hero->memberClubs()->first();
        if (! $club) { $this->command?->warn("Hero {$hero->id} has no club."); return; }

        $mates = User::whereIn('id', DB::table('memberships')
                ->where('tenant_id', $club->id)->where('user_id', '!=', $hero->id)
                ->distinct()->limit(40)->pluck('user_id'))->get()->values();

        $this->command?->info("Seeding events for {$hero->full_name} @ {$club->club_name} ({$mates->count()} mates).");

        // Clean prior showcase events (cascades to categories/matches/registrations).
        ClubEvent::where('tenant_id', $club->id)
            ->whereIn('title', collect($this->events())->pluck('title'))
            ->delete();

        foreach ($this->events() as $i => $def) {
            $event = $this->makeEvent($club->id, $def);
            $this->enroll($event, $hero, $mates, $def);
            if (! empty($def['tkd'])) {
                $this->seedTaekwondo($event, $hero, $mates);
            }
        }

        $this->command?->info('Done. Events: ' . ClubEvent::where('tenant_id', $club->id)->count()
            . ' · Registrations: ' . ClubEventRegistration::whereIn('event_id', ClubEvent::where('tenant_id', $club->id)->pluck('id'))->count());
    }

    private function makeEvent(int $tenantId, array $d): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id'         => $tenantId,
            'title'             => $d['title'],
            'event_type'        => $d['type'],
            'sport'             => $d['sport'] ?? null,
            'icon'              => $d['icon'],
            'color'             => $d['color'],
            'description'       => $d['about'],
            'date'              => now()->addDays($d['in'])->toDateString(),
            'end_date'          => isset($d['endIn']) ? now()->addDays($d['endIn'])->toDateString() : null,
            'start_time'        => $d['start'],
            'end_time'          => $d['end'] ?? null,
            'location'          => $d['location'],
            'level'             => $d['level'],
            'max_capacity'      => $d['cap'],
            'participant_fee'   => $d['fee'],
            'spectator_enabled' => ! empty($d['spectator']),
            'spectator_fee'     => $d['spectator'] ?? null,
            'prize'             => $d['prize'] ?? null,
            'requirements'      => $d['requirements'] ?? null,
            'phases'            => $d['phases'] ?? null,
            'agenda'            => $d['agenda'] ?? null,
            'tags'              => $d['tags'] ?? null,
            'status'            => 'active',
            'is_archived'       => false,
        ]);
    }

    /** Enroll a believable crowd of participants + spectators. */
    private function enroll(ClubEvent $event, User $hero, $mates, array $d): void
    {
        $isQualified = str_contains(strtolower((string) $event->participant_fee), 'qualified');
        $paidPart = $event->participant_fee && ! str_contains(strtolower($event->participant_fee), 'free') && ! $isQualified;

        $count = min($d['going'] ?? 10, $mates->count());
        if (! $isQualified) {
            foreach ($mates->take($count) as $i => $mate) {
                ClubEventRegistration::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $mate->id],
                    ['role' => 'participant', 'status' => 'joined', 'paid' => ! $paidPart || $i % 3 !== 0, 'registered_at' => now()->subDays(rand(0, 6))]
                );
            }
            // Hero joins a couple of events.
            if (! empty($d['heroJoins'])) {
                ClubEventRegistration::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $hero->id],
                    ['role' => 'participant', 'status' => 'joined', 'paid' => ! $paidPart, 'registered_at' => now()]
                );
            }
        }

        // Spectators.
        if ($event->spectator_enabled) {
            foreach ($mates->slice($count, $d['spectators'] ?? 8) as $mate) {
                ClubEventRegistration::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $mate->id],
                    ['role' => 'spectator', 'status' => 'joined', 'paid' => true, 'registered_at' => now()->subDays(rand(0, 4))]
                );
            }
        }
    }

    /* ----------------- Taekwondo brackets ----------------- */

    private function seedTaekwondo(ClubEvent $event, User $hero, $mates): void
    {
        // Men −58 kg — LIVE bracket.
        $m58 = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Men −58 kg', 'weight_class' => 'Fin weight',
            'capacity' => 8, 'status' => 'live', 'note' => 'Semi-finals in progress on Mat 1', 'sort_order' => 1,
        ]);
        $this->matches($event->id, $m58->id, [
            ['Quarter-finals', 'Mat 1', '10:00', 'done', 'a', ['Omar Khalid','BHR',1,'2'], ['Diego Santos','BRA',8,'0']],
            ['Quarter-finals', 'Mat 1', '10:30', 'done', 'b', ['Min-jun Park','KOR',4,'1'], ['Ivan Petrov','RUS',5,'2']],
            ['Quarter-finals', 'Mat 2', '11:00', 'done', 'a', ['Carlos Ruiz','ESP',3,'2'], ['Yuki Tanaka','JPN',6,'1']],
            ['Quarter-finals', 'Mat 2', '11:30', 'done', 'a', ['Ahmed Saleh','EGY',2,'2'], ['Sami Haddad','LBN',7,'0']],
            ['Semi-finals', 'Mat 1', '14:00', 'live', null, ['Omar Khalid','BHR',1,'1'], ['Ivan Petrov','RUS',5,'1']],
            ['Semi-finals', 'Mat 1', '14:45', 'upcoming', null, ['Carlos Ruiz','ESP',3,'–'], ['Ahmed Saleh','EGY',2,'–']],
            ['Final', 'Mat 1', '17:00', 'upcoming', null, ['Winner SF1','',null,'–'], ['Winner SF2','',null,'–']],
        ]);

        // Men −68 kg — COMPLETED with podium.
        $m68 = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Men −68 kg', 'weight_class' => 'Feather weight',
            'capacity' => 8, 'status' => 'completed', 'note' => 'Completed — Jin-ho Lee takes gold', 'sort_order' => 2,
            'podium' => [
                ['place' => 1, 'name' => 'Jin-ho Lee', 'country' => 'KOR', 'prize' => '$10,000 + Gold'],
                ['place' => 2, 'name' => 'Mehdi Karimi', 'country' => 'IRI', 'prize' => '$5,000 + Silver'],
                ['place' => 3, 'name' => 'Tariq Bin Saad', 'country' => 'KSA', 'prize' => '$2,500 + Bronze'],
            ],
        ]);
        $this->matches($event->id, $m68->id, [
            ['Semi-finals', 'Mat 2', '15:00', 'done', 'a', ['Jin-ho Lee','KOR',1,'2'], ['Tariq Bin Saad','KSA',4,'1']],
            ['Semi-finals', 'Mat 2', '15:45', 'done', 'b', ['Luca Moretti','ITA',3,'0'], ['Mehdi Karimi','IRI',2,'2']],
            ['Final', 'Mat 1', '18:00', 'done', 'a', ['Jin-ho Lee','KOR',1,'2'], ['Mehdi Karimi','IRI',2,'1']],
        ]);

        // Women −49 kg — ENROLLING (roster + open slots from real members).
        $w49 = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Women −49 kg', 'weight_class' => 'Fin weight',
            'capacity' => 8, 'status' => 'enrolling', 'note' => 'Bracket is drawn after weigh-in', 'sort_order' => 3,
        ]);
        $countries = ['BHR', 'JPN', 'RUS', 'EGY', 'BHR'];
        foreach ($mates->take(5) as $i => $mate) {
            ClubEventRegistration::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $mate->id],
                ['role' => 'participant', 'status' => 'joined', 'paid' => true, 'category_id' => $w49->id,
                 'meta' => $countries[$i] ?? 'BHR', 'registered_at' => now()->subDays(rand(0, 5))]
            );
        }
    }

    private function matches(int $eventId, int $catId, array $rows): void
    {
        foreach ($rows as $slot => $r) {
            EventMatch::create([
                'event_id' => $eventId, 'category_id' => $catId, 'round' => $r[0], 'slot' => $slot,
                'court' => $r[1], 'scheduled_time' => $r[2], 'status' => $r[3], 'winner' => $r[4],
                'a_name' => $r[5][0], 'a_country' => $r[5][1], 'a_seed' => $r[5][2], 'a_score' => $r[5][3],
                'b_name' => $r[6][0], 'b_country' => $r[6][1], 'b_seed' => $r[6][2], 'b_score' => $r[6][3],
            ]);
        }
    }

    /* ----------------- Event catalog ----------------- */

    private function events(): array
    {
        return [
            [
                'title' => 'Summer Sprint Cup', 'type' => 'race', 'sport' => 'running', 'icon' => 'bi-lightning-charge-fill', 'color' => '#7c3aed',
                'in' => 5, 'start' => '16:00', 'end' => '19:30', 'location' => 'Main Stadium · Manama', 'level' => 'Open',
                'cap' => 60, 'going' => 48, 'fee' => null, 'spectator' => null, 'prize' => 'Medals · Top 3 each category',
                'heroJoins' => true,
                'about' => 'A high-energy 100m & 200m sprint competition open to all club members. Heats run through the afternoon with a finals showdown under the lights.',
                'tags' => ['Sprint', 'Outdoor', 'Competitive'],
                'agenda' => [['t' => '4:00 PM', 'd' => 'Check-in & warm-up'], ['t' => '4:45 PM', 'd' => 'Qualifying heats'], ['t' => '6:00 PM', 'd' => 'Semi-finals'], ['t' => '6:45 PM', 'd' => 'Finals & medals']],
            ],
            [
                'title' => 'Karate Belt Grading', 'type' => 'belt_test', 'sport' => 'karate', 'icon' => 'bi-patch-check-fill', 'color' => '#f59e0b',
                'in' => 9, 'start' => '17:00', 'end' => '19:00', 'location' => 'Dojo · Block 412', 'level' => 'White → Brown',
                'cap' => 24, 'going' => 18, 'fee' => 'BHD 10', 'spectator' => 'Free', 'spectators' => 12,
                'about' => 'Official belt examination graded by Sensei Tariq. Demonstrate your kata, kihon and kumite to advance. The grading fee covers your assessment, new belt and certificate. Family welcome to watch free.',
                'tags' => ['Grading', 'Indoor', 'Official'],
                'requirements' => ['Minimum 3 months at your current belt', 'Clean gi and current belt', 'Know your full kata sequence', 'Grading fee paid before exam day'],
                'agenda' => [['t' => '5:00 PM', 'd' => 'Line-up & warm-up'], ['t' => '5:20 PM', 'd' => 'Kihon assessment'], ['t' => '6:00 PM', 'd' => 'Kata demonstration'], ['t' => '6:30 PM', 'd' => 'Kumite & belt ceremony']],
            ],
            [
                'title' => 'Strength & Conditioning', 'type' => 'class', 'icon' => 'bi-heart-pulse-fill', 'color' => '#10b981',
                'in' => 14, 'start' => '07:00', 'end' => '08:00', 'location' => 'Gym Floor 1', 'level' => 'All',
                'cap' => 25, 'going' => 15, 'fee' => null, 'spectator' => null, 'heroJoins' => true,
                'about' => 'An early-morning full-body conditioning session to build power and endurance. Suitable for all levels.',
                'tags' => ['Fitness', 'Indoor', 'Morning'],
                'agenda' => [['t' => '7:00 AM', 'd' => 'Mobility & activation'], ['t' => '7:20 AM', 'd' => 'Strength circuit'], ['t' => '7:45 AM', 'd' => 'Conditioning finisher']],
            ],
            [
                'title' => 'Club Boxing Championship', 'type' => 'championship', 'sport' => 'boxing', 'icon' => 'bi-trophy-fill', 'color' => '#ef4444',
                'in' => 23, 'start' => '18:00', 'end' => '22:00', 'location' => 'Main Arena · Manama', 'level' => 'Amateur',
                'cap' => 48, 'going' => 32, 'fee' => 'BHD 15', 'spectator' => 'BHD 5', 'spectators' => 20, 'prize' => 'BHD 500 + Championship Belt',
                'about' => 'The annual club boxing championship — three weight divisions, full amateur rules, ringside doctor and certified referees. Fighters pay an entry fee; spectators buy a ticket to watch ringside.',
                'tags' => ['Boxing', 'Ticketed', 'Prize'],
                'agenda' => [['t' => '6:00 PM', 'd' => 'Doors open · weigh-in check'], ['t' => '6:45 PM', 'd' => 'Lightweight bouts'], ['t' => '7:45 PM', 'd' => 'Welterweight bouts'], ['t' => '8:45 PM', 'd' => 'Heavyweight bouts'], ['t' => '9:30 PM', 'd' => 'Finals & belt ceremony']],
            ],
            [
                'title' => 'Open Padel Tournament', 'type' => 'tournament', 'sport' => 'padel', 'icon' => 'bi-trophy', 'color' => '#0ea5e9',
                'in' => 30, 'start' => '09:00', 'end' => '18:00', 'location' => 'Padel Courts 1–4', 'level' => 'Open · Doubles',
                'cap' => 32, 'going' => 28, 'fee' => 'BHD 20 / team', 'spectator' => 'Free', 'spectators' => 10, 'prize' => 'BHD 300 + trophies',
                'about' => 'A full-day open doubles padel tournament with group stages and knockout rounds. Register as a team of two — the fee covers court time, balls, referees and lunch. Spectators watch free.',
                'tags' => ['Padel', 'Doubles', 'Open', 'Prize'],
                'agenda' => [['t' => '9:00 AM', 'd' => 'Group stage begins'], ['t' => '12:30 PM', 'd' => 'Lunch break'], ['t' => '1:30 PM', 'd' => 'Knockout rounds'], ['t' => '5:00 PM', 'd' => 'Finals & prize giving']],
            ],
            [
                'title' => 'Grand Championship — Finals Night', 'type' => 'championship', 'icon' => 'bi-award-fill', 'color' => '#7c3aed',
                'in' => 37, 'start' => '19:00', 'end' => '22:30', 'location' => 'Grand Hall · Manama', 'level' => 'Elite',
                'cap' => 16, 'going' => 16, 'fee' => 'Qualified finalists', 'spectator' => 'BHD 8', 'spectators' => 30, 'prize' => 'BHD 1,000 + Grand Trophy',
                'about' => 'The season finale — only the 16 qualified finalists compete, so participation is by qualification. A marquee spectator night: buy a ticket to watch the finals, with a live DJ and awards gala.',
                'tags' => ['Elite', 'Ticketed', 'Gala', 'Limited'],
                'agenda' => [['t' => '7:00 PM', 'd' => 'Doors & red carpet'], ['t' => '7:45 PM', 'd' => 'Finals — round 1'], ['t' => '9:00 PM', 'd' => 'Championship finals'], ['t' => '10:00 PM', 'd' => 'Awards gala']],
            ],
            [
                'title' => 'World Taekwondo Championship', 'type' => 'championship', 'sport' => 'taekwondo', 'icon' => 'bi-trophy-fill', 'color' => '#6d28d9',
                'in' => 35, 'endIn' => 37, 'start' => '09:00', 'end' => '21:00', 'location' => 'National Arena · Manama', 'level' => 'International',
                'cap' => 128, 'going' => 96, 'fee' => 'BHD 25', 'spectator' => 'BHD 10', 'spectators' => 24, 'prize' => '$10,000 + World Title', 'tkd' => true,
                'about' => 'The official World Taekwondo Championship — Olympic-style sparring across weight categories. Athletes compete in single-elimination brackets seeded after the official weigh-in. Spectators can buy a 3-day pass.',
                'tags' => ['Taekwondo', 'International', 'Ticketed', 'Olympic-style'],
                'requirements' => ['Valid national federation license', 'Make weight at the official weigh-in (Jul 22)', 'WT-approved protective gear', 'Entry fee paid before enrollment closes'],
                'phases' => [
                    ['label' => 'Enrollment opens', 'date' => 'Jul 1', 'icon' => 'bi-megaphone', 'status' => 'done', 'note' => 'Registration & fee payment'],
                    ['label' => 'Enrollment closes', 'date' => 'Jul 20', 'icon' => 'bi-door-closed', 'status' => 'active', 'note' => 'Last day to sign up'],
                    ['label' => 'Official weigh-in', 'date' => 'Jul 22', 'icon' => 'bi-speedometer2', 'status' => 'upcoming', 'note' => 'Make your category weight'],
                    ['label' => 'Draw & brackets', 'date' => 'Jul 22', 'icon' => 'bi-diagram-3', 'status' => 'upcoming', 'note' => 'Seeding & bracket creation'],
                    ['label' => 'Competition days', 'date' => 'Jul 24–25', 'icon' => 'bi-trophy', 'status' => 'upcoming', 'note' => 'Prelims through semi-finals'],
                    ['label' => 'Finals & medals', 'date' => 'Jul 26', 'icon' => 'bi-award-fill', 'status' => 'upcoming', 'note' => 'Finals, podium & prizes'],
                ],
                'agenda' => [['t' => 'Jul 24', 'd' => 'Preliminary rounds — all categories'], ['t' => 'Jul 25', 'd' => 'Quarter-finals & semi-finals'], ['t' => 'Jul 26', 'd' => 'Finals, medal ceremony & prizes']],
            ],
        ];
    }
}
