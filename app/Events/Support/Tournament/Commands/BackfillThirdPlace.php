<?php

namespace App\Events\Support\Tournament\Commands;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Sports\Combat\Engine\DrawEngine;
use App\Sports\Combat\SportRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give a division that was cut without one its play-off for bronze.
 *
 * ⚠️ WHY THIS EXISTS. A sport's `bronzeRule()` is read at the moment a draw is
 * CUT, and nothing revisits it afterwards. Brazilian jiu-jitsu's was set to
 * `both_sf_losers` and back to `third_place_match` inside one day
 * (2026-09-18 — the reasoning is written out in BrazilianJiuJitsu::bronzeRule),
 * so every division drawn in that window has no Third place bout and never
 * will: the two beaten semi-finalists simply both take bronze, on a
 * championship that awards three places and expects them to fight for the
 * third.
 *
 * Re-cutting the draw would answer it and would also throw away every result
 * already recorded. This adds the missing bout instead, and nothing else:
 *
 *   · only a division that HAS a semi-final and has NO Third place row;
 *   · only for an event whose sport says `third_place_match`;
 *   · the bout is placed on the final's slot and the final moves one along, so
 *     it runs BEFORE the final exactly as a freshly cut draw would put it;
 *   · both corners are filled from whoever has already lost a semi-final, the
 *     same reading Advancement::syncThirdPlace() makes.
 *
 * A division whose podium is already awarded is REPORTED AND LEFT ALONE. Its
 * medals are a record, and re-opening third place is somebody's decision, not
 * a migration's.
 *
 * Dry by default. `--apply` writes, inside a transaction, and RULE #2 applies:
 * take a backup first.
 */
class BackfillThirdPlace extends Command
{
    protected $signature = 'combat:third-place-backfill
        {event? : an event uuid or id — omit for every event}
        {--apply : write the bouts (otherwise this only reports)}
        {--include-awarded : also re-open a division whose podium was already awarded}';

    protected $description = 'Add the missing play-off for bronze to divisions cut without one';

    public function handle(SportRegistry $sports): int
    {
        $events = ClubEvent::query()
            ->when($this->argument('event'), fn ($q, $key) => $q->where('uuid', $key)->orWhere('id', $key))
            ->get()
            ->filter(fn (ClubEvent $e) => $sports->get($e->sport)?->bronzeRule() === 'third_place_match');

        if ($events->isEmpty()) {
            $this->warn('No event found whose sport plays off for bronze.');

            return self::SUCCESS;
        }

        $added = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $this->line("<info>{$event->title}</info> ({$event->uuid})");

            foreach (EventCategory::where('event_id', $event->id)->orderBy('id')->get() as $category) {
                $semis = $category->matches()->where('round', 'Semifinal')->orderBy('slot')->get();

                if ($semis->isEmpty()) {
                    continue;   // too small a field to lose a semi-final
                }

                if ($category->matches()->where('round', DrawEngine::ROUND_THIRD)->exists()) {
                    continue;   // already has one
                }

                $awarded = ! empty($category->podium);

                /*
                 * A division whose medals are already given out is left alone
                 * by default: re-opening third place voids a bronze somebody
                 * has been told they won, and that is the owner's decision,
                 * not a backfill's. `--include-awarded` is them making it.
                 */
                if ($awarded && ! $this->option('include-awarded')) {
                    $this->line("  <comment>·</comment> {$category->name} — podium already awarded, left alone");
                    $skipped++;

                    continue;
                }

                $this->line($awarded
                    ? "  <comment>!</comment> {$category->name} — RE-OPENING an awarded podium"
                    : "  <comment>+</comment> {$category->name}");

                $added++;

                if (! $this->option('apply')) {
                    continue;
                }

                DB::transaction(function () use ($event, $category, $semis, $awarded) {
                    /*
                     * The stored podium claimed two bronzes, which is the
                     * convention this division is being taken OUT of. Cleared
                     * so nothing reads a medal that is now to be fought for;
                     * Advancement re-awards the whole podium — gold, silver,
                     * third and fourth — the moment the play-off lands.
                     */
                    if ($awarded) {
                        $category->update(['podium' => [], 'status' => 'live']);
                    }

                    $final = $category->matches()->where('round', 'Final')->orderBy('slot')->first();
                    $slot = $final?->slot ?? ($category->matches()->max('slot') + 1);

                    // The play-off takes the final's place in the order, and the
                    // final moves one along behind it.
                    if ($final) {
                        $final->slot = $slot + 1;
                        $final->save();
                    }

                    $third = $category->matches()->create([
                        'event_id' => $event->id,
                        'round' => DrawEngine::ROUND_THIRD,
                        'phase' => 'finals',
                        'slot' => $slot,
                        'status' => 'upcoming',
                    ]);

                    // Whoever has already lost a semi-final stands in the bout;
                    // an undecided semi-final leaves its corner empty, and
                    // Advancement fills it when that bout lands.
                    foreach ([0 => 'a', 1 => 'b'] as $i => $side) {
                        $semi = $semis[$i] ?? null;

                        if (! $semi instanceof EventMatch || ! $semi->winner) {
                            continue;
                        }

                        $lost = $semi->winner === 'a' ? 'b' : 'a';

                        $third->{$side.'_name'} = $semi->{$lost.'_name'};
                        $third->{$side.'_competitor_id'} = $semi->{$lost.'_competitor_id'};
                        $third->{$side.'_country'} = $semi->{$lost.'_country'};
                        $third->{$side.'_provisional'} = (bool) $semi->{$lost.'_provisional'};
                    }

                    $third->save();
                });
            }
        }

        $this->newLine();
        $this->info($this->option('apply')
            ? "Added {$added} play-off(s) for bronze. {$skipped} division(s) left alone."
            : "Would add {$added} play-off(s) for bronze. {$skipped} division(s) would be left alone. Re-run with --apply.");

        return self::SUCCESS;
    }
}
