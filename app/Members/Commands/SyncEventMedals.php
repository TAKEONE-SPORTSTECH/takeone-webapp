<?php

namespace App\Members\Commands;

use App\Events\Models\EventCategory;
use App\Members\Models\PerformanceResult;
use App\Members\Support\ProfileHistorySync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put the medals of divisions ALREADY decided onto their medallists' profiles —
 * the catch-up for results recorded before EventCategoryObserver existed.
 *
 * DRY RUN BY DEFAULT: the whole pass runs inside a transaction that is rolled
 * back, and reports what it would have written. --write commits it.
 *
 * Idempotent: syncPodium() replaces only the medals a division wrote itself,
 * so a second run changes nothing.
 */
class SyncEventMedals extends Command
{
    protected $signature = 'takeone:sync-event-medals
        {--event= : Limit to one event, by numeric id}
        {--write : Actually write the medals (default is a dry run)}';

    protected $description = 'Give medallists the profile medals their event podiums imply';

    public function handle(ProfileHistorySync $sync): int
    {
        $write = (bool) $this->option('write');

        // The operator asking IS the switch for this one process.
        config(['events.profile_medals' => true]);

        $this->line($write ? 'WRITING medals.' : 'DRY RUN — nothing will be kept. Re-run with --write to apply.');

        $divisions = EventCategory::query()
            ->whereNotNull('podium')
            ->when($this->option('event'), fn ($q, $id) => $q->where('event_id', (int) $id))
            ->get()
            ->filter(fn (EventCategory $c) => ! empty($c->podium));

        DB::beginTransaction();
        try {
            $before = PerformanceResult::whereNotNull('source_category_id')->count();
            $total = 0;
            foreach ($divisions as $division) {
                $n = $sync->syncPodium($division);
                $total += $n;
                $this->line(sprintf('  event %-5d %-45s %d medal(s)', $division->event_id, mb_substr((string) $division->name, 0, 45), $n));
            }
            $after = PerformanceResult::whereNotNull('source_category_id')->count();
            $this->info("Divisions: {$divisions->count()} · medals on profiles: {$total} (event medal rows {$before} → {$after})");

            $write ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
