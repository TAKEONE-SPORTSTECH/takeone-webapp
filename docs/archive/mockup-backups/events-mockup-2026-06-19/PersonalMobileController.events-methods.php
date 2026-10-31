<?php

namespace Documentation\Backups\EventsMockup20260619;

use Illuminate\View\View;

/**
 * SNAPSHOT (2026-06-19) of the /me/events mockup controller logic.
 * Source: app/Http/Controllers/PersonalMobileController.php
 * Methods: events(), eventShow(), eventBracket(), demoEvents(), demoTkdCategories().
 *
 * Reference copy only — NOT autoloaded/executed. To restore, paste these
 * methods back into PersonalMobileController (which already imports View and
 * uses $this->demoEvents() etc.). The 3 Blade views are in this same folder.
 */
class EventsMockupSnapshot
{
    public function events(): View
    {
        // DUMMY: curated sample events for design preview. Swap for the real
        // ClubEvent query (commented below) when the feature is wired in.
        $demo = $this->demoEvents();

        return view('personal.events', compact('demo'));
    }

    /**
     * DUMMY event detail page. Renders a curated sample event so the detail
     * view can be designed/previewed before the real ClubEvent feed is wired in.
     * Swap $events lookup for a real ClubEvent query when ready.
     */
    public function eventShow(int $event): View
    {
        $events = $this->demoEvents();
        $e = $events[$event] ?? $events[array_key_first($events)];

        return view('personal.event-show', ['e' => $e]);
    }

    /** DUMMY tournament brackets page (weight categories, draws, matches, podiums). */
    public function eventBracket(int $event): View
    {
        $events = $this->demoEvents();
        $e = $events[$event] ?? $events[array_key_first($events)];

        return view('personal.event-bracket', ['e' => $e, 'categories' => $e['categories'] ?? []]);
    }

    /** Shared curated dummy events (keyed by id) for the events list + detail. */
    public function demoEvents(): array
    {
        // Schema notes:
        //   type            human label (Class / Race / Belt Test / Tournament / Championship …)
        //   participant_fee what it costs to take part ('Free' or 'BHD x')
        //   spectator       null, or ['fee' => 'BHD x' | 'Free', 'count' => int] — pay-to-watch
        //   participants    people already signed up (name + meta like belt/seed)
        //   prize / divisions / requirements   extra blocks for comps & belt tests
        return [
            1 => [
                'id' => 1, 'day' => '24', 'mon' => 'Jun', 'wday' => 'Sat',
                'title' => 'Summer Sprint Cup', 'club' => 'Eta Athletics Club',
                'location' => 'Main Stadium · Manama', 'address' => 'Isa Town Sports City, Manama, Bahrain',
                'time' => '4:00 PM', 'end' => '7:30 PM', 'level' => 'Open', 'tag' => 'Race', 'type' => 'Competition',
                'icon' => 'bi-lightning-charge-fill', 'color' => '#7c3aed', 'going' => 48, 'cap' => 60,
                'participant_fee' => 'Free', 'spectator' => null, 'duration' => '3h 30m',
                'prize' => 'Medals · Top 3 each category',
                'about' => 'A high-energy 100m & 200m sprint competition open to all club members. Heats run through the afternoon with a finals showdown under the lights. Medals for the top three in each category, plus refreshments and a post-race social.',
                'agenda' => [
                    ['t' => '4:00 PM', 'd' => 'Check-in & warm-up'],
                    ['t' => '4:45 PM', 'd' => 'Qualifying heats'],
                    ['t' => '6:00 PM', 'd' => 'Semi-finals'],
                    ['t' => '6:45 PM', 'd' => 'Finals & medal ceremony'],
                ],
                'tags' => ['Sprint', 'Outdoor', 'Competitive', 'All ages'],
                'participants' => [
                    ['name' => 'Layla Ahmad', 'meta' => '100m · 200m'],
                    ['name' => 'Omar Khalid', 'meta' => '100m'],
                    ['name' => 'Sara Mansour', 'meta' => '200m'],
                    ['name' => 'Yousef Hadi', 'meta' => '100m'],
                    ['name' => 'Noor Salem', 'meta' => '200m'],
                ],
            ],
            2 => [
                'id' => 2, 'day' => '28', 'mon' => 'Jun', 'wday' => 'Wed',
                'title' => 'Karate Belt Grading', 'club' => 'Eta Martial Arts',
                'location' => 'Dojo · Block 412', 'address' => 'Eta Athletics Club, Block 412, Manama',
                'time' => '5:00 PM', 'end' => '7:00 PM', 'level' => 'White → Brown', 'tag' => 'Belt Test', 'type' => 'Belt Test',
                'icon' => 'bi-patch-check-fill', 'color' => '#f59e0b', 'going' => 18, 'cap' => 24,
                'participant_fee' => 'BHD 10', 'spectator' => ['fee' => 'Free', 'count' => 40], 'duration' => '2h',
                'about' => 'Official belt examination graded by Sensei Tariq. Demonstrate your kata, kihon and kumite to advance to the next belt. The grading fee covers your assessment, new belt and certificate. Family and friends are welcome to watch for free.',
                'requirements' => [
                    'Minimum 3 months at your current belt',
                    'Clean gi and current belt',
                    'Know your full kata sequence',
                    'Grading fee paid before exam day',
                ],
                'agenda' => [
                    ['t' => '5:00 PM', 'd' => 'Line-up & warm-up'],
                    ['t' => '5:20 PM', 'd' => 'Kihon (basics) assessment'],
                    ['t' => '6:00 PM', 'd' => 'Kata demonstration'],
                    ['t' => '6:30 PM', 'd' => 'Kumite & belt ceremony'],
                ],
                'tags' => ['Grading', 'Indoor', 'Official'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Green → Blue'],
                    ['name' => 'Maya Tariq', 'meta' => 'White → Yellow'],
                    ['name' => 'Ali Faris', 'meta' => 'Blue → Brown'],
                    ['name' => 'Sara Mansour', 'meta' => 'Yellow → Orange'],
                ],
            ],
            3 => [
                'id' => 3, 'day' => '03', 'mon' => 'Jul', 'wday' => 'Mon',
                'title' => 'Strength & Conditioning', 'club' => 'Eta Athletics Club',
                'location' => 'Gym Floor 1', 'address' => 'Eta Athletics Club, Block 412, Manama',
                'time' => '7:00 AM', 'end' => '8:00 AM', 'level' => 'All', 'tag' => 'Class', 'type' => 'Class',
                'icon' => 'bi-heart-pulse-fill', 'color' => '#10b981', 'going' => 15, 'cap' => 25,
                'participant_fee' => 'Free', 'spectator' => null, 'duration' => '1h',
                'about' => 'An early-morning full-body conditioning session to build power and endurance. Suitable for all levels — scale up or down with the coach.',
                'agenda' => [
                    ['t' => '7:00 AM', 'd' => 'Mobility & activation'],
                    ['t' => '7:20 AM', 'd' => 'Strength circuit'],
                    ['t' => '7:45 AM', 'd' => 'Conditioning finisher'],
                ],
                'tags' => ['Fitness', 'Indoor', 'Morning'],
                'participants' => [
                    ['name' => 'Dana Wael', 'meta' => 'Member'],
                    ['name' => 'Khalid Bader', 'meta' => 'Member'],
                ],
            ],
            4 => [
                'id' => 4, 'day' => '12', 'mon' => 'Jul', 'wday' => 'Wed',
                'title' => 'Club Boxing Championship', 'club' => 'Eta Boxing',
                'location' => 'Main Arena · Manama', 'address' => 'Eta Arena, Seef District, Manama, Bahrain',
                'time' => '6:00 PM', 'end' => '10:00 PM', 'level' => 'Amateur', 'tag' => 'Championship', 'type' => 'Championship',
                'icon' => 'bi-trophy-fill', 'color' => '#ef4444', 'going' => 32, 'cap' => 48,
                'participant_fee' => 'BHD 15', 'spectator' => ['fee' => 'BHD 5', 'count' => 210], 'duration' => '4h',
                'prize' => 'BHD 500 + Championship Belt',
                'about' => 'The annual club boxing championship — three weight divisions, full amateur rules, ringside doctor and certified referees. Fighters pay an entry fee that covers medicals, gloves and wraps. Spectators can buy a ticket to watch all the bouts ringside. Concessions available all night.',
                'divisions' => ['Lightweight (−60 kg)', 'Welterweight (−69 kg)', 'Heavyweight (+81 kg)'],
                'agenda' => [
                    ['t' => '6:00 PM', 'd' => 'Doors open · weigh-in check'],
                    ['t' => '6:45 PM', 'd' => 'Lightweight bouts'],
                    ['t' => '7:45 PM', 'd' => 'Welterweight bouts'],
                    ['t' => '8:45 PM', 'd' => 'Heavyweight bouts'],
                    ['t' => '9:30 PM', 'd' => 'Finals & belt ceremony'],
                ],
                'tags' => ['Boxing', 'Ticketed', 'Competitive', 'Prize'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Welterweight · #2 seed'],
                    ['name' => 'Hassan Tariq', 'meta' => 'Heavyweight · #1 seed'],
                    ['name' => 'Ali Faris', 'meta' => 'Lightweight'],
                    ['name' => 'Saad Mubarak', 'meta' => 'Welterweight'],
                    ['name' => 'Khalid Bader', 'meta' => 'Heavyweight'],
                ],
            ],
            5 => [
                'id' => 5, 'day' => '19', 'mon' => 'Jul', 'wday' => 'Sat',
                'title' => 'Open Padel Tournament', 'club' => 'Eta Racquet Club',
                'location' => 'Padel Courts 1–4', 'address' => 'Eta Racquet Club, Janabiyah, Bahrain',
                'time' => '9:00 AM', 'end' => '6:00 PM', 'level' => 'Open · Doubles', 'tag' => 'Tournament', 'type' => 'Tournament',
                'icon' => 'bi-trophy', 'color' => '#0ea5e9', 'going' => 28, 'cap' => 32,
                'participant_fee' => 'BHD 20 / team', 'spectator' => ['fee' => 'Free', 'count' => 75], 'duration' => 'Full day',
                'prize' => 'BHD 300 + trophies',
                'about' => 'A full-day open doubles padel tournament with group stages and knockout rounds. Register as a team of two — the fee covers court time, balls, referees and lunch. Open to all clubs and the public. Spectators watch free all day.',
                'divisions' => ['Mixed Doubles', 'Men’s Doubles', 'Women’s Doubles'],
                'agenda' => [
                    ['t' => '9:00 AM', 'd' => 'Group stage begins'],
                    ['t' => '12:30 PM', 'd' => 'Lunch break'],
                    ['t' => '1:30 PM', 'd' => 'Knockout rounds'],
                    ['t' => '5:00 PM', 'd' => 'Finals & prize giving'],
                ],
                'tags' => ['Padel', 'Doubles', 'Open', 'Prize'],
                'participants' => [
                    ['name' => 'Reem & Lina', 'meta' => 'Women’s · #1 seed'],
                    ['name' => 'Fahad & Saad', 'meta' => 'Men’s'],
                    ['name' => 'Omar & Yousef', 'meta' => 'Men’s · #2 seed'],
                    ['name' => 'Dana & Maya', 'meta' => 'Mixed'],
                ],
            ],
            6 => [
                'id' => 6, 'day' => '26', 'mon' => 'Jul', 'wday' => 'Sat',
                'title' => 'Grand Championship — Finals Night', 'club' => 'Eta Athletics Club',
                'location' => 'Grand Hall · Manama', 'address' => 'Eta Arena, Seef District, Manama, Bahrain',
                'time' => '7:00 PM', 'end' => '10:30 PM', 'level' => 'Elite', 'tag' => 'Championship', 'type' => 'Championship',
                'icon' => 'bi-award-fill', 'color' => '#7c3aed', 'going' => 16, 'cap' => 16,
                'participant_fee' => 'Qualified finalists', 'spectator' => ['fee' => 'BHD 8', 'count' => 430], 'duration' => '3h 30m',
                'prize' => 'BHD 1,000 + Grand Trophy',
                'about' => 'The season finale — only the 16 qualified finalists compete, so participation is by qualification, not sign-up. This is a marquee spectator night: buy a ticket to watch the finals across all disciplines, with a live DJ, awards gala and after-party. Tickets are limited.',
                'agenda' => [
                    ['t' => '7:00 PM', 'd' => 'Doors & red carpet'],
                    ['t' => '7:45 PM', 'd' => 'Finals — round 1'],
                    ['t' => '9:00 PM', 'd' => 'Championship finals'],
                    ['t' => '10:00 PM', 'd' => 'Awards gala & after-party'],
                ],
                'tags' => ['Elite', 'Ticketed', 'Gala', 'Limited'],
                'participants' => [
                    ['name' => 'Layla Ahmad', 'meta' => 'Sprint finalist'],
                    ['name' => 'Hassan Tariq', 'meta' => 'Boxing finalist'],
                    ['name' => 'Reem Al Najjar', 'meta' => 'Swim finalist'],
                ],
            ],
            7 => [
                'id' => 7, 'day' => '24', 'mon' => 'Jul', 'wday' => 'Thu',
                'title' => 'World Taekwondo Championship', 'club' => 'World Taekwondo Federation',
                'location' => 'National Arena · Manama', 'address' => 'Khalifa Sports City, Manama, Bahrain',
                'time' => '9:00 AM', 'end' => '9:00 PM', 'level' => 'International', 'tag' => 'World Championship', 'type' => 'World Championship',
                'icon' => 'bi-trophy-fill', 'color' => '#6d28d9', 'going' => 96, 'cap' => 128,
                'participant_fee' => 'BHD 25', 'spectator' => ['fee' => 'BHD 10', 'count' => 1240], 'duration' => '3 days',
                'prize' => '$10,000 + World Title',
                'about' => 'The official World Taekwondo Championship — Olympic-style sparring across six weight categories for both men and women. Athletes compete in single-elimination brackets seeded after the official weigh-in. Entry fee covers registration, medicals and equipment check. Spectators can buy a 3-day pass to watch every bout across all mats.',
                'divisions' => ['Men −58kg', 'Men −68kg', 'Men −80kg', 'Women −49kg', 'Women −57kg', 'Women −67kg'],
                'requirements' => [
                    'Valid national federation license',
                    'Make weight at the official weigh-in (Jul 22)',
                    'WT-approved protective gear (hogu, helmet, guards)',
                    'Entry fee paid before enrollment closes (Jul 20)',
                ],
                // Event lifecycle: enrollment → weigh-in → draw → competition → finals.
                'phases' => [
                    ['label' => 'Enrollment opens',      'date' => 'Jul 1',     'icon' => 'bi-megaphone',     'status' => 'done',     'note' => 'Registration & fee payment'],
                    ['label' => 'Enrollment closes',     'date' => 'Jul 20',    'icon' => 'bi-door-closed',   'status' => 'active',   'note' => 'Last day to sign up'],
                    ['label' => 'Official weigh-in',     'date' => 'Jul 22',    'icon' => 'bi-speedometer2',  'status' => 'upcoming', 'note' => 'Make your category weight'],
                    ['label' => 'Draw & brackets',       'date' => 'Jul 22',    'icon' => 'bi-diagram-3',     'status' => 'upcoming', 'note' => 'Seeding & bracket creation'],
                    ['label' => 'Competition days',      'date' => 'Jul 24–25', 'icon' => 'bi-trophy',        'status' => 'upcoming', 'note' => 'Prelims through semi-finals'],
                    ['label' => 'Finals & medals',       'date' => 'Jul 26',    'icon' => 'bi-award-fill',    'status' => 'upcoming', 'note' => 'Finals, podium & prizes'],
                ],
                'agenda' => [
                    ['t' => 'Jul 24', 'd' => 'Preliminary rounds — all categories'],
                    ['t' => 'Jul 25', 'd' => 'Quarter-finals & semi-finals'],
                    ['t' => 'Jul 26', 'd' => 'Finals, medal ceremony & prizes'],
                ],
                'tags' => ['Taekwondo', 'International', 'Ticketed', 'Olympic-style'],
                'participants' => [
                    ['name' => 'Omar Khalid', 'meta' => 'Men −58kg · #1 seed · BHR'],
                    ['name' => 'Ahmed Saleh', 'meta' => 'Men −58kg · #2 seed · EGY'],
                    ['name' => 'Min-jun Park', 'meta' => 'Men −58kg · #4 seed · KOR'],
                    ['name' => 'Jin-ho Lee', 'meta' => 'Men −68kg · #1 seed · KOR'],
                    ['name' => 'Sara Mansour', 'meta' => 'Women −49kg · BHR'],
                ],
                // Weight categories — each with its own enrollment + bracket state.
                'categories' => $this->demoTkdCategories(),
            ],
        ];
    }

    /** DUMMY taekwondo weight categories with brackets / rosters / podiums. */
    public function demoTkdCategories(): array
    {
        return [
            'm58' => [
                'key' => 'm58', 'name' => 'Men −58 kg', 'class' => 'Fin weight',
                'cap' => 8, 'joined' => 8, 'open' => 0, 'status' => 'live',
                'note' => 'Semi-finals in progress on Mat 1',
                'rounds' => [
                    ['name' => 'Quarter-finals', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '10:00', 'status' => 'done', 'winner' => 'a',
                         'a' => ['name' => 'Omar Khalid',  'country' => 'BHR', 'seed' => 1, 'score' => '2'],
                         'b' => ['name' => 'Diego Santos',  'country' => 'BRA', 'seed' => 8, 'score' => '0']],
                        ['court' => 'Mat 1', 'time' => '10:30', 'status' => 'done', 'winner' => 'b',
                         'a' => ['name' => 'Min-jun Park',  'country' => 'KOR', 'seed' => 4, 'score' => '1'],
                         'b' => ['name' => 'Ivan Petrov',   'country' => 'RUS', 'seed' => 5, 'score' => '2']],
                        ['court' => 'Mat 2', 'time' => '11:00', 'status' => 'done', 'winner' => 'a',
                         'a' => ['name' => 'Carlos Ruiz',   'country' => 'ESP', 'seed' => 3, 'score' => '2'],
                         'b' => ['name' => 'Yuki Tanaka',   'country' => 'JPN', 'seed' => 6, 'score' => '1']],
                        ['court' => 'Mat 2', 'time' => '11:30', 'status' => 'done', 'winner' => 'a',
                         'a' => ['name' => 'Ahmed Saleh',   'country' => 'EGY', 'seed' => 2, 'score' => '2'],
                         'b' => ['name' => 'Sami Haddad',   'country' => 'LBN', 'seed' => 7, 'score' => '0']],
                    ]],
                    ['name' => 'Semi-finals', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '14:00', 'status' => 'live', 'winner' => null,
                         'a' => ['name' => 'Omar Khalid', 'country' => 'BHR', 'seed' => 1, 'score' => '1'],
                         'b' => ['name' => 'Ivan Petrov', 'country' => 'RUS', 'seed' => 5, 'score' => '1']],
                        ['court' => 'Mat 1', 'time' => '14:45', 'status' => 'upcoming', 'winner' => null,
                         'a' => ['name' => 'Carlos Ruiz', 'country' => 'ESP', 'seed' => 3, 'score' => '–'],
                         'b' => ['name' => 'Ahmed Saleh', 'country' => 'EGY', 'seed' => 2, 'score' => '–']],
                    ]],
                    ['name' => 'Final', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '17:00', 'status' => 'upcoming', 'winner' => null,
                         'a' => ['name' => 'Winner SF1', 'country' => '', 'seed' => null, 'score' => '–'],
                         'b' => ['name' => 'Winner SF2', 'country' => '', 'seed' => null, 'score' => '–']],
                    ]],
                ],
            ],
            'm68' => [
                'key' => 'm68', 'name' => 'Men −68 kg', 'class' => 'Feather weight',
                'cap' => 8, 'joined' => 8, 'open' => 0, 'status' => 'completed',
                'note' => 'Completed — Jin-ho Lee takes gold',
                'podium' => [
                    ['place' => 1, 'name' => 'Jin-ho Lee',   'country' => 'KOR', 'prize' => '$10,000 + Gold'],
                    ['place' => 2, 'name' => 'Mehdi Karimi',  'country' => 'IRI', 'prize' => '$5,000 + Silver'],
                    ['place' => 3, 'name' => 'Tariq Bin Saad', 'country' => 'KSA', 'prize' => '$2,500 + Bronze'],
                ],
                'rounds' => [
                    ['name' => 'Semi-finals', 'matches' => [
                        ['court' => 'Mat 2', 'time' => '15:00', 'status' => 'done', 'winner' => 'a',
                         'a' => ['name' => 'Jin-ho Lee',     'country' => 'KOR', 'seed' => 1, 'score' => '2'],
                         'b' => ['name' => 'Tariq Bin Saad', 'country' => 'KSA', 'seed' => 4, 'score' => '1']],
                        ['court' => 'Mat 2', 'time' => '15:45', 'status' => 'done', 'winner' => 'b',
                         'a' => ['name' => 'Luca Moretti',   'country' => 'ITA', 'seed' => 3, 'score' => '0'],
                         'b' => ['name' => 'Mehdi Karimi',   'country' => 'IRI', 'seed' => 2, 'score' => '2']],
                    ]],
                    ['name' => 'Final', 'matches' => [
                        ['court' => 'Mat 1', 'time' => '18:00', 'status' => 'done', 'winner' => 'a',
                         'a' => ['name' => 'Jin-ho Lee',   'country' => 'KOR', 'seed' => 1, 'score' => '2'],
                         'b' => ['name' => 'Mehdi Karimi', 'country' => 'IRI', 'seed' => 2, 'score' => '1']],
                    ]],
                ],
            ],
            'w49' => [
                'key' => 'w49', 'name' => 'Women −49 kg', 'class' => 'Fin weight',
                'cap' => 8, 'joined' => 5, 'open' => 3, 'status' => 'enrolling',
                'note' => 'Bracket is drawn after weigh-in (Jul 22)',
                'roster' => [
                    ['name' => 'Sara Mansour',  'country' => 'BHR'],
                    ['name' => 'Hana Suzuki',   'country' => 'JPN'],
                    ['name' => 'Elena Volkova',  'country' => 'RUS'],
                    ['name' => 'Mariam Sayed',   'country' => 'EGY'],
                    ['name' => 'Noor Salem',     'country' => 'BHR'],
                ],
            ],
        ];
    }
}
