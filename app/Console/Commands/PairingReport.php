<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * "I am holding a code, the screen shows it, why will it not pair?"
 *
 * One command, readable in a hall, on a phone, by somebody who has ten minutes
 * before the first match. It answers the question the message on the console
 * cannot: what that code actually IS on this server.
 */
class PairingReport extends Command
{
    protected $signature = 'takeone:pairing
                            {--lines=40 : How many attempts to show}
                            {--failed : Only the refusals}
                            {--code= : Only this pairing code}';

    protected $description = 'Recent screen and camera pairing attempts, and why they failed';

    public function handle(): int
    {
        $files = glob(storage_path('logs/pairing-*.log')) ?: [];
        rsort($files);

        if (! $files) {
            $this->warn('  Nothing logged yet. Open /screen, or the camera app, and try to pair.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($files as $file) {
            foreach (array_reverse(file($file, FILE_IGNORE_NEW_LINES)) as $line) {
                if (! preg_match('/^\[(?<at>[^\]]+)\].*?(?<lvl>INFO|WARNING|ERROR): (?<msg>[^\{]+)(?<ctx>\{.*\})?\s*$/', $line, $m)) {
                    continue;
                }

                $ctx = json_decode($m['ctx'] ?? '{}', true) ?: [];

                if ($this->option('failed') && ($ctx['outcome'] ?? '') !== 'refused') {
                    continue;
                }
                if (($only = $this->option('code')) && strtoupper($only) !== ($ctx['code'] ?? '')) {
                    continue;
                }

                $rows[] = [
                    substr($m['at'], 5, 14),
                    $ctx['code'] ?? '—',
                    $ctx['outcome'] ?? trim($m['msg']),
                    $ctx['device'] ?? $ctx['surface'] ?? '—',
                    $ctx['code_is'] ?? '—',
                    $this->host($ctx['host'] ?? ''),
                    $ctx['reason'] ?? '',
                ];

                if (count($rows) >= (int) $this->option('lines')) {
                    break 2;
                }
            }
        }

        if (! $rows) {
            $this->warn('  No matching attempts.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['when', 'code', 'outcome', 'wanted', 'what the code IS here', 'host', 'reason'], $rows);

        $this->line('');
        $this->line('  <fg=gray>A code that is "nothing on this server" while somebody is plainly');
        $this->line('  reading it off a screen belongs to the OTHER TAKEONE host.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function host(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }
}
