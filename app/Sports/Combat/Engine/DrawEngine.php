<?php

namespace App\Sports\Combat\Engine;

use App\Models\ClubEvent;
use App\Models\EventCategory;

/**
 * Single-elimination draw generation + lifecycle for a combat championship.
 * Sport-agnostic: stable seeded spread, byes to the next power of two, and the
 * provisional → final → locked lifecycle.
 */
class DrawEngine
{
    public function __construct(private Scheduler $scheduler) {}

    /**
     * Keep each division's draw current:
     *  - before start: a PROVISIONAL draw including unpaid entrants (rebuilt when the entrant set changes)
     *  - at/after start: a FINAL paid-only draw, built once then locked
     * Manually-built brackets (no draw_state but existing matches) are never touched.
     */
    public function ensure(ClubEvent $event): void
    {
        if (! $event->isCombat() || $event->hasEnded()) {
            return;
        }

        $started = $event->hasStarted();
        $changed = false;

        foreach ($event->categories()->withCount('matches')->get() as $cat) {
            if ($cat->draw_state === null && $cat->matches_count > 0) {
                continue; // hand-built bracket
            }

            if ($started) {
                if ($cat->draw_state === 'final') {
                    continue; // locked
                }
                $this->build($event, $cat, paidOnly: true);
                $changed = true;
            } else {
                $entrants = $cat->registrations()->where('role', 'participant')->count();
                if ($cat->draw_state === 'provisional' && $cat->draw_count === $entrants) {
                    continue; // already fresh
                }
                if ($entrants < 2) {
                    continue;
                }
                $this->build($event, $cat, paidOnly: false);
                $changed = true;
            }
        }

        if ($changed) {
            $this->scheduler->scheduleAndNumber($event);
        }
    }

    /**
     * (Re)build a single-elimination bracket for a division. Entrants are spread by
     * a stable pseudo-random order (changes only when the entrant set changes — no
     * reshuffle jitter); field padded to the next power of two with byes that auto-advance.
     */
    public function build(ClubEvent $event, EventCategory $cat, bool $paidOnly): void
    {
        // Final (at-start) draw: only confirmed athletes compete — paid AND weighed in.
        $regs = $cat->registrations()
            ->where('role', 'participant')
            ->when($paidOnly, fn ($q) => $q->where('paid', true)->whereNotNull('weight'))
            ->with('user:id,full_name,name')
            ->get();

        // Stable pseudo-random spread; provisional = at risk of removal at start (unpaid OR not weighed in).
        $competitors = $regs->map(fn ($r) => [
            'name' => $r->user?->full_name ?? $r->user?->name ?? 'Athlete',
            'provisional' => ! $paidOnly && (! $r->paid || $r->weight === null),
            'key' => md5($cat->id.':'.$r->user_id),
        ])->sortBy('key')->values();

        $cat->matches()->delete();

        $n = $competitors->count();
        if ($n < 2) {
            $cat->update(['draw_state' => $paidOnly ? 'final' : 'provisional', 'draw_count' => $n]);

            return;
        }

        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }

        // Standard bracket-seed slots so byes are spread out.
        $slots = array_fill(0, $size, null);
        foreach ($this->seedOrder($size) as $slotIndex => $seedNum) {
            if ($seedNum <= $n) {
                $slots[$slotIndex] = $competitors[$seedNum - 1];
            }
        }

        $rounds = $this->roundNames($size);
        $slot = 0;

        for ($i = 0; $i < $size; $i += 2) {
            $a = $slots[$i];
            $b = $slots[$i + 1];
            $bye = (($a === null) xor ($b === null));
            $cat->matches()->create([
                'event_id' => $event->id,
                'round' => $rounds[0],
                'phase' => $this->phaseForRound($rounds[0]),
                'slot' => $slot++,
                'a_name' => $a['name'] ?? null,
                'a_provisional' => $a['provisional'] ?? false,
                'b_name' => $b['name'] ?? null,
                'b_provisional' => $b['provisional'] ?? false,
                'winner' => $bye ? ($a ? 'a' : 'b') : null,
                'status' => $bye ? 'done' : 'upcoming',
            ]);
        }

        for ($r = 1; $r < count($rounds); $r++) {
            $count = $size / (2 ** ($r + 1));
            for ($i = 0; $i < $count; $i++) {
                $cat->matches()->create([
                    'event_id' => $event->id,
                    'round' => $rounds[$r],
                    'phase' => $this->phaseForRound($rounds[$r]),
                    'slot' => $slot++,
                    'status' => 'upcoming',
                ]);
            }
        }

        $cat->update([
            'draw_state' => $paidOnly ? 'final' : 'provisional',
            'draw_count' => $n,
            'schedule' => $cat->schedule ?: $this->scheduler->defaultSchedule($event, $cat),
            'status' => ($paidOnly && $cat->status === 'enrolling') ? 'live' : $cat->status,
        ]);
    }

    /** Standard single-elimination seed positions (index = slot, value = seed #). */
    public function seedOrder(int $size): array
    {
        $seeds = [1];
        $rounds = (int) log($size, 2);
        for ($r = 0; $r < $rounds; $r++) {
            $sum = (2 ** ($r + 1)) + 1;
            $next = [];
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $sum - $s;
            }
            $seeds = $next;
        }

        return $seeds;
    }

    /** Round labels for a power-of-two bracket, largest first. */
    public function roundNames(int $size): array
    {
        $names = [];
        for ($m = $size / 2; $m >= 1; $m /= 2) {
            $names[] = match ((int) $m) {
                1 => 'Final',
                2 => 'Semifinal',
                4 => 'Quarterfinal',
                default => 'Round of '.((int) $m * 2),
            };
        }

        return $names;
    }

    /** Group a round into one of the 3 schedulable phases. */
    public function phaseForRound(string $round): string
    {
        if (str_starts_with($round, 'Round of')) {
            return 'preliminary';
        }

        return match ($round) {
            'Quarterfinal' => 'quarterfinals',
            'Semifinal', 'Final' => 'finals',
            default => 'preliminary',
        };
    }
}
