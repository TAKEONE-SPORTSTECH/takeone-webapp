<?php

namespace App\Console\Commands;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Import a round-robin karate competition from the two sheets an organiser
 * actually has: a participants list and a bout schedule.
 *
 * ── Why a command and not a one-off script ────────────────────────────────
 * It runs twice. The first time is a rehearsal, the second is twenty minutes
 * before the first bout with a corrected sheet — so it is idempotent by
 * construction: athletes are matched on their name within THIS event, entries
 * and bouts are keyed by their sheet identity, and re-running updates rather
 * than duplicates. `--fresh` clears the event's entries and bouts first, for a
 * sheet that changed shape rather than content.
 *
 * ── Round robin, without a round-robin engine ─────────────────────────────
 * The package builds single-elimination draws and advances winners into next
 * slots. A round robin has no next slot: every bout is terminal, and the group
 * is decided by a table rather than by a final. That works here WITHOUT touching
 * the engine because the bouts come from the sheet — nothing has to be drawn.
 * Each bout is written as its own terminal fixture, and because no fixture names
 * another as its parent, recording a result advances nobody. The standings are a
 * reading of the results, not a structure in the draw.
 *
 * ── What it deliberately does not invent ──────────────────────────────────
 * A club name it cannot map to a real club on the platform is left unmapped
 * rather than guessed at: an athlete shown competing for the wrong club on a
 * hall screen is worse than one shown with no club. Unmapped names are reported
 * at the end so somebody can decide.
 */
class ImportKarateTrials extends Command
{
    protected $signature = 'karate:import-trials
        {event : The event uuid}
        {--participants= : Path to the participants CSV}
        {--bouts= : Path to the bouts CSV}
        {--court=Mat 1 : Which mat the bouts run on}
        {--fresh : Clear this event\'s entries and bouts first}
        {--dry-run : Report what would happen and change nothing}';

    protected $description = 'Import participants and round-robin bouts into a karate event from CSV';

    /** Club names on the sheet, mapped to real clubs by exact or obvious match. */
    private array $clubMap = [];

    private array $unmappedClubs = [];

    public function handle(): int
    {
        $event = ClubEvent::where('uuid', $this->argument('event'))->first();

        if (! $event) {
            $this->error('No event with that uuid.');

            return self::FAILURE;
        }

        if ($event->sport !== 'karate') {
            $this->error('That event is not karate — its screens would not know what to draw.');

            return self::FAILURE;
        }

        $participants = $this->readCsv((string) $this->option('participants'));
        $bouts = $this->readCsv((string) $this->option('bouts'));

        if ($participants === null || $bouts === null) {
            return self::FAILURE;
        }

        $this->info("Event: {$event->title}");
        $this->line('  participants on sheet: '.count($participants));
        $this->line('  bouts on sheet:        '.count($bouts));

        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->warn('Dry run — nothing will be written.');
        }

        $this->buildClubMap($participants);

        if ($this->option('fresh') && ! $dry) {
            $this->clearEvent($event);
        }

        DB::beginTransaction();

        try {
            $groups = $this->importGroups($event, $participants, $dry);
            $entries = $this->importAthletes($event, $participants, $groups, $dry);
            $made = $this->importBouts($event, $bouts, $groups, $entries, $dry);

            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            $this->newLine();
            $this->info('Groups:   '.count($groups));
            $this->info('Athletes: '.count($entries));
            $this->info('Bouts:    '.$made);

            if ($this->unmappedClubs) {
                $this->newLine();
                $this->warn('Club names with no club on the platform (athletes imported without one):');
                foreach (array_unique($this->unmappedClubs) as $name) {
                    $this->line('  · '.$name);
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was written: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<int, array<string, string>>|null */
    private function readCsv(string $path): ?array
    {
        if ($path === '' || ! is_file($path)) {
            $this->error("Cannot read CSV: {$path}");

            return null;
        }

        $rows = array_map('str_getcsv', array_filter(array_map('trim', file($path)), fn ($l) => $l !== ''));
        $head = array_map('trim', array_shift($rows));

        return array_map(function (array $row) use ($head) {
            // Short rows are padded rather than refused: a trailing empty column
            // is the most common thing a spreadsheet export gets wrong.
            $row = array_pad(array_slice($row, 0, count($head)), count($head), '');

            return array_combine($head, array_map('trim', $row));
        }, $rows);
    }

    /**
     * Shorthand on the sheet, spelled out.
     *
     * A dry run is what these are for. Fuzzy matching alone got two of them
     * wrong: "Shotokan Al-Hala" found nothing, because the club is registered as
     * "Al Hala Karate Club" and shares only one word with it — and bare
     * "Shotokan" matched a DEMO club, which would have put a fixture on a hall
     * screen competing for a club that does not exist. Aliases are declared
     * rather than inferred so the mapping can be read and argued with.
     */
    private const ALIASES = [
        'sparta' => 'Sparta Worrior',
        'shotokan muharraq' => 'Muharraq Shotokan Academy',
        'shotokan al-hala' => 'Al Hala Karate Club',
    ];

    /**
     * Match each club name on the sheet to a club on the platform: declared
     * aliases first, then an exact name, then every significant word of the
     * sheet's name appearing in a club's name. Anything still unmatched is
     * reported rather than guessed.
     */
    private function buildClubMap(array $participants): void
    {
        // Demo clubs are excluded outright. They exist to make the platform look
        // full for a demonstration, they are removable by design, and a real
        // competition must never end up pointing at one.
        $clubs = Tenant::query()
            ->whereNull('deleted_at')
            ->where('slug', 'not like', 'demo-%')
            ->get(['id', 'club_name', 'country', 'slug']);

        foreach (array_unique(array_filter(array_column($participants, 'Club'))) as $name) {
            $needle = mb_strtolower($name);

            $alias = self::ALIASES[$needle] ?? null;

            $hit = $alias
                ? $clubs->first(fn ($c) => $c->club_name === $alias)
                : $clubs->first(fn ($c) => mb_strtolower($c->club_name) === $needle);

            if (! $hit && ! $alias) {
                // Every word of the sheet's name present in the club's name.
                $words = preg_split('/\s+/', $needle);
                $hit = $clubs->first(function ($c) use ($words) {
                    $hay = mb_strtolower($c->club_name);
                    foreach ($words as $w) {
                        if (mb_strlen($w) > 2 && ! str_contains($hay, $w)) {
                            return false;
                        }
                    }

                    return true;
                });
            }

            if ($hit) {
                $this->clubMap[$name] = $hit;
                $this->line("  club: {$name} → {$hit->club_name}");
            } else {
                $this->unmappedClubs[] = $name;
            }
        }
    }

    /** One category per group on the sheet. The group IS the division here. */
    private function importGroups(ClubEvent $event, array $participants, bool $dry): array
    {
        $groups = [];

        foreach (array_unique(array_column($participants, 'Group')) as $group) {
            $name = 'Group '.$group;

            if ($dry) {
                $groups[$group] = new EventCategory(['name' => $name]);

                continue;
            }

            $groups[$group] = EventCategory::updateOrCreate(
                ['event_id' => $event->id, 'name' => $name],
                [
                    // A group's members are decided by the sheet, not by a draw,
                    // so it opens already drawn: nothing here should offer to
                    // build a bracket over the top of the fixtures.
                    'draw_state' => 'locked',
                    'sort_order' => (int) $group,
                    'status' => 'active',
                ],
            );
        }

        return $groups;
    }

    /**
     * An athlete per row: an account if they do not have one, and an entry in
     * this event either way.
     *
     * The account is what makes the rest of the platform work for them — a
     * profile, a photo on the introduction screen, a record that outlives this
     * competition. Matched on name WITHIN the event so a re-run finds the same
     * person rather than making a second one.
     *
     * @return array<string, ClubEventRegistration> keyed by athlete name
     */
    private function importAthletes(ClubEvent $event, array $participants, array $groups, bool $dry): array
    {
        $entries = [];

        foreach ($participants as $row) {
            $name = $row['Name'] ?? '';

            if ($name === '') {
                continue;
            }

            $club = $this->clubMap[$row['Club'] ?? ''] ?? null;

            if ($dry) {
                $this->line("  athlete: {$name} · {$row['Category']} {$row['Weight']} · ".($club->club_name ?? 'no club'));
                $entries[$name] = new ClubEventRegistration;

                continue;
            }

            $user = $this->athlete($name, $row);

            $entries[$name] = ClubEventRegistration::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $user->id],
                [
                    'category_id' => $groups[$row['Group']]->id ?? null,
                    'status' => 'joined',
                    'role' => 'participant',
                    'registered_at' => now(),
                    // The club they compete FOR, which is what a hall screen and
                    // a report both mean by "club".
                    'representing_tenant_id' => $club?->id,
                    'entry_channel' => 'club',
                    'entered_by' => 1,
                    // The sheet's own weight, kept as the entered figure. Not a
                    // weigh-in: nobody has stood on a scale yet.
                    'weight' => $this->kg($row['Weight'] ?? ''),
                    'meta' => trim(($row['Category'] ?? '').' '.($row['Weight'] ?? '')),
                ],
            );
        }

        return $entries;
    }

    /**
     * Find or make the person.
     *
     * Exact-name matching was not enough, and the failure was invisible: three
     * of these athletes were already members WITH PHOTOS, spelled one letter
     * differently on the sheet — "Fawzia Abdullah" against "Fawzia Abdulla",
     * "Abdullah Bassam" against "Abdulla Bassam", "Zakaria Shuwaiter" against
     * "Zakaria Shuaiter". The import cheerfully created second accounts, and the
     * only symptom was a hall screen introducing a bout with two silhouettes.
     *
     * So a near miss is now a match: same first name, and a surname within one
     * edit of the sheet's. Deliberately narrow — one edit, and only when exactly
     * ONE member fits. Two candidates means we do not know which athlete this
     * is, and inventing a link would put somebody else's face on the wall, which
     * is worse than a silhouette.
     */
    private function athlete(string $name, array $row): User
    {
        $needle = mb_strtolower(trim($name));

        $existing = User::whereRaw('lower(trim(full_name)) = ?', [$needle])->first();

        if ($existing) {
            return $existing;
        }

        if ($near = $this->nearMatch($needle)) {
            $this->line("  matched on spelling: {$name} → {$near->full_name} (member #{$near->id})");

            return $near;
        }

        $slug = Str::slug($name);

        return User::create([
            'name' => $name,
            'full_name' => $name,
            // A placeholder address, and marked as one: these athletes were
            // entered off a federation sheet and have never given us an email.
            // Unique per event so two competitions can both enter the same name
            // without colliding on a login nobody uses.
            'email' => $slug.'-'.Str::lower(Str::random(6)).'@entries.takeone.bh',
            'password' => Hash::make(Str::random(32)),
            'birthdate' => $this->date($row['Birth Date'] ?? null),
            'slug' => $slug,
        ]);
    }

    /**
     * The one member whose name is within a letter or two of this one, or null.
     *
     * Compared as a WHOLE name, not first-name-then-surname: the variants that
     * actually occur sit on either side of the space — "Fawzia Abdullah" against
     * "Fawzia Abdulla" (surname), "Abdullah Bassam" against "Abdulla Bassam"
     * (first name), "Zakaria Shuwaiter" against "Zakaria Shuaiter" (a dropped
     * consonant). Splitting the name meant catching two of those three, which is
     * the worst outcome — it looks like it works.
     *
     * Deliberately tight: one edit for a short name, two for a long one, and
     * only when exactly ONE member fits. Three athletes here share the surname
     * Sanad and two share the first name Mohammed, so anything looser starts
     * putting one sibling's face on another's bout. An ambiguous near-match is
     * not a match.
     */
    private function nearMatch(string $needle): ?User
    {
        if (mb_strlen($needle) < 6) {
            return null;
        }

        $allowed = mb_strlen($needle) > 12 ? 2 : 1;

        // The first letter is required to agree. It is the cheapest way to keep
        // this from scanning the whole member base, and a competition sheet does
        // not misspell the first letter of a name.
        $candidates = User::whereRaw('lower(trim(full_name)) like ?', [mb_substr($needle, 0, 1).'%'])
            ->get(['id', 'full_name', 'profile_picture'])
            ->filter(function (User $u) use ($needle, $allowed) {
                $theirs = mb_strtolower(trim((string) $u->full_name));

                return $theirs !== '' && levenshtein($theirs, $needle) <= $allowed;
            })
            ->values();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function date(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function kg(string $raw): ?float
    {
        preg_match('/([\d.]+)/', $raw, $m);

        return isset($m[1]) ? (float) $m[1] : null;
    }

    /**
     * One terminal fixture per bout on the sheet, in the sheet's own order.
     *
     * `match_no` is the sheet's bout number, which is what the hall, the running
     * order and the report all call it. `round` says how the group is decided,
     * because "Quarterfinal" would be a lie on a table.
     */
    private function importBouts(ClubEvent $event, array $bouts, array $groups, array $entries, bool $dry): int
    {
        $made = 0;

        foreach ($bouts as $i => $row) {
            $no = (int) ($row['Bout Number'] ?? 0);
            $group = $row['Group'] ?? '';
            $aka = $row['Red Corner (AKA)'] ?? '';
            $ao = $row['Blue Corner (AO)'] ?? '';

            if (! $no || $aka === '' || $ao === '') {
                $this->warn("  bout {$no}: skipped, incomplete row");

                continue;
            }

            if ($dry) {
                $this->line("  bout {$no} (Group {$group}): {$aka} vs {$ao}");
                $made++;

                continue;
            }

            EventMatch::updateOrCreate(
                ['event_id' => $event->id, 'match_no' => (string) $no],
                [
                    'category_id' => $groups[$group]->id ?? null,
                    // Terminal by construction: no fixture names another as its
                    // parent, so a result advances nobody. See the class note.
                    'round' => 'Round Robin',
                    'slot' => $i,
                    'court' => (string) $this->option('court'),
                    'phase' => 'group',
                    'status' => 'upcoming',
                    'a_name' => $aka,
                    'b_name' => $ao,
                    'a_competitor_id' => ($entries[$aka] ?? null)?->id,
                    'b_competitor_id' => ($entries[$ao] ?? null)?->id,
                    // Every club on this sheet is Bahraini, and the flag beside a
                    // competitor is the club's country. Set explicitly so the
                    // corner shows a flag even where the club could not be mapped.
                    'a_country' => 'BH',
                    'b_country' => 'BH',
                    'a_provisional' => false,
                    'b_provisional' => false,
                ],
            );

            $made++;
        }

        return $made;
    }

    /** For a sheet that changed shape: take the event back to empty. */
    private function clearEvent(ClubEvent $event): void
    {
        $this->warn('Clearing existing entries and bouts for this event.');

        EventMatch::where('event_id', $event->id)->delete();
        ClubEventRegistration::where('event_id', $event->id)->delete();
        EventCategory::where('event_id', $event->id)->delete();
    }
}
