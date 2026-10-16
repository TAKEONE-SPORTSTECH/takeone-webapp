<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\EntryClub;
use App\Sports\Combat\BeltRank;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Facades\Log;

/**
 * Shuffle — a FAIR random draw for one knockout division (owner, 2026-10-14).
 *
 * The method most federations and draw tools use (IJF/WT seeding, WAKO and
 * JudoShiai/Smoothcomp club separation), in three rules, strongest first:
 *
 *   1. SEEDS. The highest belts are placed on the standard seed lines — 1 and
 *      2 in opposite halves, 3 and 4 in different quarters — 2 seeds up to 8
 *      entrants, 4 up to 16, 8 above. Byes fall to the seeds.
 *   2. CLUBS. The biggest club is placed first, each athlete on the free spot
 *      that shares the smallest block with a teammate: never the same bout if
 *      it can be helped, then not the same quarter, then not the same half.
 *   3. RANDOM. Everything left is chance; among equally good spots it also
 *      avoids a round-one belt gap of three or more levels.
 *
 * Greedy placement can corner itself, so one press draws TRIES candidates and
 * keeps the fairest (still random: any of the best may win). An athlete with
 * no belt on file is simply not seeded and not counted for gaps; an athlete
 * with no club is nobody's teammate.
 *
 * It decides WHERE people stand and nothing else. The board itself — byes,
 * bout rows, later rounds, the bye walking forward — is cut by DrawEngine,
 * exactly as a fresh draw is, and the division is then marked `manual` so no
 * housekeeping pass re-cuts it back to the old order. Belt ranks come from
 * the event's own sport ladder (BeltRank), so this is the same for every sport.
 */
class FairDraw
{
    public const TRIES = 30;

    /** A round-one belt gap this many ladder steps wide is avoided when it can be. */
    public const BIG_GAP = 3;

    public function __construct(private DrawEngine $draws, private BeltRank $belts) {}

    /**
     * Re-cut one division fairly. Before the event starts only, and never a
     * division that has been fought — the engine refuses that too, this says so.
     *
     * @return array{ok: bool, message?: string, category?: EventCategory}
     */
    public function shuffle(ClubEvent $event, EventCategory $category): array
    {
        if ($event->hasStarted() || $event->hasEnded()) {
            return ['ok' => false, 'message' => __('events::events.shuffle_started')];
        }

        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            return ['ok' => false, 'message' => __('events::events.shuffle_pool')];
        }

        if ($category->matches()->contested()->exists()) {
            return ['ok' => false, 'message' => __('events::events.shuffle_fought')];
        }

        $people = $this->people($event, $category);
        if (count($people) < 2) {
            return ['ok' => false, 'message' => __('events::events.shuffle_too_few')];
        }

        $order = $this->bestSlots($people);

        $this->draws->build($event, $category, paidOnly: false, order: $order);
        $category->update(['draw_state' => 'manual', 'podium' => null]);

        Log::info('Draw shuffled', ['event' => $event->uuid, 'category' => $category->id, 'by' => auth()->id(), 'entrants' => count($people)]);

        return ['ok' => true, 'category' => $category->fresh()];
    }

    /**
     * Who is drawn, with what the rules need: the entry id, a club key and a
     * belt rank on the event's sport ladder (null when unknown).
     *
     * @return list<array{id: int, club: ?string, belt: ?int}>
     */
    public function people(ClubEvent $event, EventCategory $category): array
    {
        $regs = $category->registrations()->where('role', 'participant')
            ->with(['user', 'representingTenant:id,club_name', 'user.memberClubs:id,club_name'])
            ->get();

        $written = EntryClub::forMany($event->id, $regs->pluck('id'));
        $ladder = array_flip(array_column(BeltRank::ladder($event->sport), 'value'));

        return $regs->map(function (ClubEventRegistration $r) use ($written, $ladder) {
            $tenant = $r->competingClub();
            $club = $tenant ? 't'.$tenant->id
                : (($name = trim((string) ($written[$r->id]['name'] ?? ''))) !== '' ? 'w'.mb_strtolower($name) : null);
            $colour = $r->user ? ($this->belts->for($r->user, $r)['colour'] ?? null) : null;

            return ['id' => (int) $r->id, 'club' => $club, 'belt' => $colour !== null ? ($ladder[$colour] ?? null) : null];
        })->values()->all();
    }

    /**
     * The fairest of TRIES random candidates.
     *
     * @param  list<array{id: int, club: ?string, belt: ?int}>  $people
     * @return list<?int>  entry id per bracket slot (null = bye), length a power of two
     */
    public function bestSlots(array $people): array
    {
        $best = null;
        $bestScore = PHP_INT_MAX;

        for ($k = 0; $k < self::TRIES; $k++) {
            $slots = $this->slots($people);
            $m = $this->score($slots);
            $s = $m['clash'] * 1_000_000 + $m['shared_quarter'] * 1_000 + $m['gap'];
            if ($s < $bestScore) {
                [$best, $bestScore] = [$slots, $s];
            }
        }

        return array_map(fn (?array $p) => $p['id'] ?? null, $best);
    }

    /**
     * One random candidate draw.
     *
     * @param  list<array{id: int, club: ?string, belt: ?int}>  $people
     * @return list<?array{id: int, club: ?string, belt: ?int}>
     */
    public function slots(array $people): array
    {
        $n = count($people);
        $size = 2;
        while ($size < $n) {
            $size *= 2;
        }

        // seedOrder: slot index => seed number. Seed numbers past the field are byes.
        $order = $this->draws->seedOrder($size);
        $lineOf = array_flip($order);
        $slots = array_fill(0, $size, null);
        $bye = array_map(fn (int $seed) => $seed > $n, $order);

        // Rule 1 — seeds: highest belts first, equal belts in random order.
        $seedCount = min($n, $n <= 8 ? 2 : ($n <= 16 ? 4 : 8));
        $ranked = $this->shuffled(array_values(array_filter($people, fn ($p) => $p['belt'] !== null)));
        usort($ranked, fn ($a, $b) => $b['belt'] <=> $a['belt']);
        $seeded = [];
        foreach (array_slice($ranked, 0, $seedCount) as $k => $p) {
            $slots[$lineOf[$k + 1]] = $p;
            $seeded[$p['id']] = true;
        }

        // Rule 2 — biggest club first, its athletes in random order.
        $groups = [];
        foreach ($this->shuffled(array_values(array_filter($people, fn ($p) => ! isset($seeded[$p['id']])))) as $p) {
            $groups[$p['club'] ?? '#'.$p['id']][] = $p;
        }
        $keys = $this->shuffled(array_keys($groups));
        usort($keys, fn ($a, $b) => count($groups[$b]) <=> count($groups[$a]));

        foreach ($keys as $key) {
            foreach ($groups[$key] as $p) {
                // Rule 3 — the least "badness"; ties at random.
                $best = PHP_FLOAT_MAX;
                $candidates = [];
                for ($i = 0; $i < $size; $i++) {
                    if ($slots[$i] !== null || $bye[$i]) {
                        continue;
                    }
                    $bad = $this->badness($slots, $i, $p, $size);
                    if ($bad < $best - 1e-9) {
                        [$best, $candidates] = [$bad, [$i]];
                    } elseif (abs($bad - $best) < 1e-9) {
                        $candidates[] = $i;
                    }
                }
                $slots[$candidates[random_int(0, count($candidates) - 1)]] = $p;
            }
        }

        return $slots;
    }

    /**
     * How bad a spot is for this athlete: a teammate in the same bout costs the
     * most, the same quarter less, the same half least; then a big belt gap
     * against the round-one opponent, weighed below every club rule.
     */
    private function badness(array $slots, int $i, array $p, int $size): float
    {
        $bad = 0.0;

        if ($p['club'] !== null) {
            for ($block = 2, $w = 100000.0; $block <= intdiv($size, 2); $block *= 2, $w /= 10) {
                $start = intdiv($i, $block) * $block;
                for ($j = $start; $j < $start + $block; $j++) {
                    if ($slots[$j] !== null && $slots[$j]['club'] === $p['club']) {
                        $bad += $w;
                    }
                }
            }
        }

        $opp = $slots[$i ^ 1];
        if ($opp !== null && $opp['belt'] !== null && $p['belt'] !== null) {
            $gap = abs($opp['belt'] - $p['belt']);
            $bad += $gap >= self::BIG_GAP ? $gap * 4 : 0;
        }

        return $bad;
    }

    /**
     * What a draw gets wrong, counted: teammates in one round-one bout,
     * teammates sharing a quarter, round-one belt gaps of BIG_GAP or more.
     *
     * @param  list<?array{id: int, club: ?string, belt: ?int}>  $slots
     * @return array{clash: int, shared_quarter: int, gap: int}
     */
    public function score(array $slots): array
    {
        $size = count($slots);
        $clash = $gap = $shared = 0;

        for ($i = 0; $i < $size; $i += 2) {
            [$a, $b] = [$slots[$i], $slots[$i + 1]];
            if ($a === null || $b === null) {
                continue;
            }
            if ($a['club'] !== null && $a['club'] === $b['club']) {
                $clash++;
            } elseif ($a['belt'] !== null && $b['belt'] !== null && abs($a['belt'] - $b['belt']) >= self::BIG_GAP) {
                $gap++;
            }
        }

        $quarter = max(2, intdiv($size, 4));
        for ($s = 0; $s < $size; $s += $quarter) {
            $count = [];
            foreach (array_slice($slots, $s, $quarter) as $p) {
                if ($p !== null && $p['club'] !== null) {
                    $count[$p['club']] = ($count[$p['club']] ?? 0) + 1;
                }
            }
            foreach ($count as $c) {
                $shared += max(0, $c - 1);
            }
        }

        return ['clash' => $clash, 'shared_quarter' => $shared, 'gap' => $gap];
    }

    /** A cryptographically random order (Fisher–Yates on random_int). */
    private function shuffled(array $a): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }
}
