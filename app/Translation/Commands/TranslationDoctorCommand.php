<?php

namespace App\Translation\Commands;

use App\Translation\Services\TranslationDoctor;
use Illuminate\Console\Command;

/**
 * Check translation health and repair what can be repaired.
 *
 * Scheduled every five minutes (routes/console.php). Does nothing on the
 * schedule unless TRANSLATION_AUTOFIX is on; `--check` always works and never
 * changes anything, so it is safe to run by hand at any time.
 */
class TranslationDoctorCommand extends Command
{
    protected $signature = 'translate:doctor
        {--check : Diagnose only — change nothing, notify nobody}
        {--force : Repair even when TRANSLATION_AUTOFIX is off}';

    protected $description = 'Find translation failures and fix them: dead translators, missing models, stuck and failed language runs';

    public function handle(TranslationDoctor $doctor): int
    {
        $fix = ! $this->option('check');

        if ($fix && ! $this->option('force') && ! config('translation.autofix.enabled')) {
            $this->line('Translation auto-fix is off (TRANSLATION_AUTOFIX). Use --check to diagnose.');

            return self::SUCCESS;
        }

        $report = $doctor->run($fix);

        $this->line('');
        $this->line('  <options=bold>Translators</>');
        foreach ($report['providers'] as $p) {
            $colour = match ($p['state']) {
                'ok' => 'green', 'unprobed', 'refused' => 'gray', default => 'red',
            };
            $this->line("    <fg={$colour}>{$p['state']}</>  {$p['label']} · {$p['model']}".($p['detail'] ? "  — {$p['detail']}" : ''));
        }

        $this->line('');
        $this->line('  <options=bold>Language runs</>');
        if ($report['runs'] === []) {
            $this->line('    none recorded');
        }
        foreach ($report['runs'] as $r) {
            $this->line("    {$r['locale']}  {$r['state']} ({$r['tier']}) → {$r['verdict']}");
        }

        $this->line('');
        $this->line("  <options=bold>Queue</>  {$report['queue']['connection']}/{$report['queue']['queue']} · {$report['queue']['waiting']} waiting, {$report['queue']['stale']} stale");

        if ($report['actions'] !== []) {
            $this->line('');
            $this->line('  <options=bold;fg=green>Fixed</>');
            foreach ($report['actions'] as $a) {
                $this->line("    ✓ {$a}");
            }
        }

        if ($report['incidents'] !== []) {
            $this->line('');
            $this->line('  <options=bold;fg=yellow>Needs a person</>');
            foreach ($report['incidents'] as $i) {
                $this->line("    ! {$i}");
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
