<?php

namespace App\Duplicates\Commands;

use App\Duplicates\Models\DuplicateFinding;
use App\Duplicates\Support\DuplicateFixer;
use App\Duplicates\Support\DuplicateWatch;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * The duplicate watch (App\Duplicates).
 *
 * Scheduled hourly (routes/console.php). On the schedule it does nothing
 * unless DUPLICATE_WATCH is on; it fixes only when DUPLICATE_AUTOFIX is on too.
 * `--check` always works and changes nothing, so it is safe by hand at any
 * time.
 */
class WatchDuplicatesCommand extends Command
{
    protected $signature = 'duplicates:watch
        {--check : Scan and print only — write nothing, fix nothing, notify nobody}
        {--force : Run even when DUPLICATE_WATCH is off}';

    protected $description = 'Find duplicate people, clubs and entries platform-wide; fix the certain ones when allowed';

    public function handle(DuplicateWatch $watch, DuplicateFixer $fixer): int
    {
        if ($this->option('check')) {
            $found = $watch->scan();
            $this->table(['kind', 'strength', 'signature', 'reasons'], collect($found)->map(fn ($r) => [
                $r['kind'], $r['strength'], $r['signature'], implode(', ', $r['reasons']),
            ])->sortBy([[0, 'asc'], [1, 'asc']])->values()->all());
            $this->line(count($found).' found — nothing written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! DuplicateWatch::enabled()) {
            $this->line('The duplicate watch is off (DUPLICATE_WATCH). Use --check to scan without writing.');

            return self::SUCCESS;
        }

        $before = DuplicateFinding::where('status', DuplicateFinding::OPEN)->where('strength', 'likely')->pluck('id')->flip();
        $r = $watch->record();
        $this->line("Found {$r['found']} · new {$r['new']} · gone {$r['gone']} · open {$r['open']}");

        if (DuplicateFixer::enabled()) {
            $x = $fixer->run();
            $this->line("Fixed {$x['fixed']} · refused {$x['refused']}");
        }

        $this->notifyNew($before);

        return self::SUCCESS;
    }

    /** One note to each super-admin when there is something new for a person to decide. */
    private function notifyNew($before): void
    {
        $fresh = DuplicateFinding::where('status', DuplicateFinding::OPEN)->where('strength', 'likely')
            ->get(['id'])->reject(fn ($f) => isset($before[$f->id]))->count();
        if ($fresh === 0) {
            return;
        }

        $url = rescue(fn () => route('admin.duplicates.index'), null, report: false);
        User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id')
            ->each(fn ($id) => UserNotification::notifyUser((int) $id, 'duplicates_found',
                trans_choice('duplicates::messages.new_findings', $fresh, ['n' => $fresh]), [
                    'body' => __('duplicates::messages.new_findings_body'),
                    'action_url' => $url,
                    'icon' => 'bi-people',
                ]));
    }
}
