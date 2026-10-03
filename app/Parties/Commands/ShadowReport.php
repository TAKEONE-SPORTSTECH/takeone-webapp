<?php

namespace App\Parties\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What shadow mode has seen: every (check, user, subject) where the
 * relationships answered differently from the old check (PLAN.md Phase 4).
 * A module's check is cut over only when it shows nothing here. Read-only.
 */
class ShadowReport extends Command
{
    protected $signature = 'parties:shadow {--check= : only one check, e.g. club.manage}';

    protected $description = 'List disagreements between the old permission checks and App\\Parties\\Authority';

    public function handle(): int
    {
        $rows = DB::table('party_shadow_disagreements')
            ->when($this->option('check'), fn ($q, $c) => $q->where('check', $c))
            ->orderBy('check')->orderByDesc('hits')->get();

        if ($rows->isEmpty()) {
            $this->info('No disagreements recorded.');

            return self::SUCCESS;
        }

        $this->table(['check', 'user', 'subject', 'old', 'new', 'hits', 'last seen'], $rows->map(fn ($r) => [
            $r->check, $r->user_id, $r->subject, $r->old_answer ? 'yes' : 'no', $r->new_answer ? 'yes' : 'no', $r->hits, $r->last_seen_at,
        ])->all());

        return self::FAILURE;
    }
}
