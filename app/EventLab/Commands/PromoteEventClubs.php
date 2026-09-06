<?php

namespace App\EventLab\Commands;

use App\EventLab\Models\SandboxEventClub;
use App\EventLab\Services\ClubPromotion;
use App\Models\ClubEvent;
use Illuminate\Console\Command;

/**
 * The "later" in "later auto registered".
 *
 * Sweeps every event that has finished and turns each of its temporary clubs
 * into a real one — but only the clubs that actually brought somebody. Safe to
 * run as often as you like: a club it has already promoted carries
 * `promoted_tenant_id` and is never seen again.
 *
 *   php artisan eventlab:promote-clubs            # do it
 *   php artisan eventlab:promote-clubs --pretend  # say what it would do
 */
class PromoteEventClubs extends Command
{
    protected $signature = 'eventlab:promote-clubs {--pretend : List what would happen and change nothing}';

    protected $description = 'Register the clubs that competed at a finished event, and drop the ones that did not';

    public function handle(ClubPromotion $promotion): int
    {
        $eventIds = SandboxEventClub::query()
            ->whereNull('tenant_id')
            ->whereNull('promoted_tenant_id')
            ->distinct()
            ->pluck('event_id');

        if ($eventIds->isEmpty()) {
            $this->info('No temporary clubs are waiting.');

            return self::SUCCESS;
        }

        $made = 0;

        foreach (ClubEvent::whereIn('id', $eventIds)->get() as $event) {
            if (! $promotion->isOver($event)) {
                $this->line("  <fg=gray>skipping</> {$event->title} — not over yet");

                continue;
            }

            if ($this->option('pretend')) {
                $clubs = SandboxEventClub::where('event_id', $event->id)
                    ->whereNull('tenant_id')->whereNull('promoted_tenant_id')->get();

                foreach ($clubs as $club) {
                    $this->line($club->competed()
                        ? "  <fg=green>would register</> {$club->name} ({$club->entrants()->count()} athletes)"
                        : "  <fg=yellow>would drop</> {$club->name} — nobody competed");
                }

                continue;
            }

            $result = $promotion->forEvent($event);

            foreach ($result['promoted'] as $name) {
                $this->line("  <fg=green>registered</> {$name}");
                $made++;
            }

            foreach ($result['skipped'] as $name) {
                $this->line("  <fg=yellow>left</> {$name} — nobody competed");
            }
        }

        $this->info($this->option('pretend') ? 'Nothing was changed.' : "Registered {$made} club(s).");

        return self::SUCCESS;
    }
}
