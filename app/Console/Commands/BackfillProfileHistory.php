<?php

namespace App\Console\Commands;

use App\Models\ClubEventRegistration;
use App\Models\Membership;
use App\Support\ProfileHistorySync;
use Illuminate\Console\Command;

/**
 * One-off catch-up for every membership and event entry that predates the
 * profile-history sync.
 *
 * DRY RUN BY DEFAULT — it reports what it would write and changes nothing until
 * --write is passed, because this touches real member profiles.
 *
 * Safe to run repeatedly: ProfileHistorySync checks for an existing row before
 * every write, so a second run reports zero.
 */
class BackfillProfileHistory extends Command
{
    protected $signature = 'takeone:backfill-profile-history
        {--write : Actually write the rows (default is a dry run)}
        {--user= : Limit to one member, by numeric id or uuid}';

    protected $description = 'Give members the affiliation and tournament rows their real memberships and event entries imply';

    public function handle(ProfileHistorySync $sync): int
    {
        $write = (bool) $this->option('write');

        if (! $write) {
            $this->warn('DRY RUN — nothing will be written. Re-run with --write to apply.');
            $this->newLine();
        } elseif (! $this->confirm('This writes to real member profiles. Have you taken a verified backup?', false)) {
            $this->error('Aborted. Run `php artisan takeone:backup` first.');

            return self::FAILURE;
        }

        $userId = $this->resolveUser();

        $memberships = Membership::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $registrations = ClubEventRegistration::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with('event')
            ->orderBy('id')
            ->get();

        $this->info("Scanning {$memberships->count()} membership(s) and {$registrations->count()} event entr(y/ies)…");
        $this->newLine();

        $affiliations = 0;
        $tournaments  = 0;

        foreach ($memberships as $membership) {
            if ($write) {
                $made = $sync->syncMembership($membership);
                if ($made) {
                    $affiliations++;
                    $this->line("  + affiliation: member {$membership->user_id} → {$made->club_name}");
                }
            } else {
                // Dry run: ask the sync what it WOULD do without letting it write.
                \DB::beginTransaction();
                $made = $sync->syncMembership($membership);
                \DB::rollBack();

                if ($made) {
                    $affiliations++;
                    $this->line("  + affiliation: member {$membership->user_id} → {$made->club_name}");
                }
            }
        }

        foreach ($registrations as $registration) {
            if ($write) {
                $made = $sync->syncRegistration($registration);
                if ($made) {
                    $tournaments++;
                    $this->line("  + tournament: member {$registration->user_id} → {$made->title}");
                }
            } else {
                \DB::beginTransaction();
                $made = $sync->syncRegistration($registration);
                \DB::rollBack();

                if ($made) {
                    $tournaments++;
                    $this->line("  + tournament: member {$registration->user_id} → {$made->title}");
                }
            }
        }

        $this->newLine();
        $this->info(($write ? 'Wrote ' : 'Would write ')."{$affiliations} affiliation(s) and {$tournaments} tournament record(s).");

        if (! $write && ($affiliations || $tournaments)) {
            $this->comment('Re-run with --write to apply.');
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?int
    {
        $ref = $this->option('user');

        if (! $ref) {
            return null;
        }

        $user = \App\Models\User::query()
            ->where('uuid', $ref)
            ->orWhere('id', is_numeric($ref) ? (int) $ref : 0)
            ->first();

        if (! $user) {
            $this->error("No user found for [{$ref}] — scanning everyone instead would be surprising, so stopping.");
            exit(self::FAILURE);
        }

        return $user->id;
    }
}
