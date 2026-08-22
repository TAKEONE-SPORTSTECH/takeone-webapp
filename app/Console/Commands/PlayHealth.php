<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verify this platform can reach TAKEONE Play, and that it is safe to publish.
 *
 * "Safe to publish" is more than a 200. The match timeline is built entirely
 * from wall-clock stamps (VIDEO-INTEGRATION.md §4), so two hosts that disagree
 * about the time produce highlights that sit on the wrong technique — silently,
 * and only discoverable by watching the video. This command therefore checks
 * the clock as seriously as it checks the credential.
 *
 * Read-only and safe to run at any time, including during an event.
 */
class PlayHealth extends Command
{
    protected $signature = 'play:health';

    protected $description = 'Check connectivity, credentials and clock agreement with TAKEONE Play';

    public function handle(): int
    {
        $url = rtrim((string) config('play.url'), '/');
        $token = (string) config('play.token');

        $this->line('TAKEONE Play — '.$url);
        $this->newLine();

        if (! config('play.enabled')) {
            $this->line('  flag        <fg=yellow>OFF</> (PLAY_INTEGRATION_ENABLED=false)');
            $this->line('              Expected until Phase 2 is verified. Nothing calls Play while this is false.');
        } else {
            $this->line('  flag        <fg=green>ON</>');
        }

        if ($token === '') {
            $this->newLine();
            $this->error('No PLAY_API_TOKEN set. Mint one on Play: php artisan takeone:integration-token');

            return self::FAILURE;
        }

        // Sample several times and keep the exchange with the LOWEST round trip,
        // the way NTP does. A single request is useless for this: the first one
        // pays TLS setup, and that asymmetry lands entirely in the offset — an
        // early version of this command reported 437 ms of "drift" that was
        // purely its own handshake. The fastest exchange is the one whose
        // latency is most nearly symmetric, so its midpoint is the closest
        // estimate of the remote clock available without a real time protocol.
        $samples = [];
        $response = null;

        for ($i = 0; $i < 5; $i++) {
            $before = microtime(true);

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout((int) config('play.timeout'))
                    ->get($url.'/api/v1/health');
            } catch (Throwable $e) {
                $this->newLine();
                // The message may name internal hosts; the class alone is enough here.
                $this->error('Could not reach Play ('.class_basename($e).').');

                return self::FAILURE;
            }

            $after = microtime(true);

            if (! $response->successful()) {
                break;
            }

            $body = $response->json();

            if (! empty($body['server_time'])) {
                $samples[] = [
                    'rtt'    => $after - $before,
                    'theirs' => Carbon::parse($body['server_time'])->getPreciseTimestamp(3) / 1000,
                    'mid'    => ($before + $after) / 2,
                ];
            }
        }

        if ($response->status() === 401) {
            $this->newLine();
            $this->error('Play rejected the token (401). Rotate it there and update PLAY_API_TOKEN here.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->newLine();
            $this->error('Play answered '.$response->status().'.');

            return self::FAILURE;
        }

        $body = $response->json();

        $this->line('  reachable   <fg=green>yes</> ('.number_format(($after - $before) * 1000).' ms)');
        $this->line('  token       <fg=green>accepted</> — '.($body['token_name'] ?? 'unnamed'));
        $this->line('  abilities   '.implode(', ', $body['abilities'] ?? []));

        foreach (['match:read', 'match:write'] as $needed) {
            if (! in_array($needed, $body['abilities'] ?? [], true)) {
                $this->line("              <fg=yellow>missing {$needed}</>");
            }
        }

        // Compare clocks against the midpoint of the request, so the round trip
        // is excluded rather than counted as drift.
        $status = self::SUCCESS;

        if (! empty($samples)) {
            usort($samples, fn ($a, $b) => $a['rtt'] <=> $b['rtt']);
            $best = $samples[0];

            $skew = $best['theirs'] - $best['mid'];
            $bound = $best['rtt'] / 2;
            $max = (int) config('play.max_clock_skew');

            $text = sprintf('%+.3f s (± %.3f, best of %d)', $skew, $bound, count($samples));

            if (abs($skew) > $max) {
                $this->line("  clock       <fg=red>{$text}</> — exceeds the {$max}s limit");
                $this->newLine();
                $this->error('Clocks disagree by more than the tolerance. Markers would land on the wrong moment.');
                $status = self::FAILURE;
            } else {
                $this->line("  clock       <fg=green>{$text}</> — within the {$max}s limit");
            }
        }

        $this->newLine();

        return $status;
    }
}
