<?php

namespace App\Translation\Jobs;

use App\Translation\Services\ContentLocales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Translate the whole interface into one language, from a button.
 *
 * ── Why a job and not a request ─────────────────────────────────────────────
 *
 * A tier is ~5,500 strings and takes fifteen minutes to an hour depending on
 * the language — Hindi generates about three times slower than Chinese. That is
 * not something a browser can hold open, so the screen dispatches this and
 * watches `status()`.
 *
 * ── Why it shells out to the console command ────────────────────────────────
 *
 * Because there must be ONE implementation of "translate the interface"
 * (CLAUDE.md → Shared Stays Shared). The command owns the passes, the
 * straggler retries, the validator and the completeness verdict, and every one
 * of those was written because a subtly different second copy is how strings go
 * missing in the first place. A job that re-implemented any of it would drift
 * within a week.
 *
 * The command writes ROWS, not files — see the interface_translations
 * migration — so nothing here composes PHP from a model's output.
 */
class TranslateInterfaceLocale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Long by necessity: a slow language is an hour of API calls. */
    public int $timeout = 5400;

    /** One attempt. A half-finished run leaves rows behind and is resumable by
     *  re-running, which is a better answer than an automatic retry that starts
     *  from the top and spends the budget twice. */
    public int $tries = 1;

    public function __construct(
        public string $locale,
        public string $tier = 'event',
        public bool $rewrite = false,
    ) {
        // Off the default queue when one is configured — see config/translation.php.
        if ($connection = config('translation.queue')) {
            $this->onConnection($connection)->onQueue('translation');
        }
    }

    /** Where a screen looks to see whether this language is being worked on. */
    public static function statusKey(string $locale): string
    {
        return 'itr-run:'.$locale;
    }

    /** Where the running command writes how many strings it has done. */
    public static function progressKey(string $locale, ?int $lane = null): string
    {
        return 'itr-progress:'.$locale.($lane ? ':lane'.$lane : '');
    }

    /** @return array{state: string, started_at: ?string, finished_at: ?string, complete: ?bool, progress: ?array} */
    public static function status(string $locale): array
    {
        $status = (array) Cache::get(self::statusKey($locale), ['state' => 'idle']) + [
            'state' => 'idle', 'started_at' => null, 'finished_at' => null, 'complete' => null,
        ];

        $status['progress'] = null;

        // Only while running: a finished run's last count would read as stuck.
        if ($status['state'] !== 'running') {
            return $status;
        }

        // One count for a single-lane run, one per lane when it is split across
        // GPUs — summed, so the screen shows ONE bar for the language.
        $keys = [self::progressKey($locale)];

        foreach (array_keys((array) config('translation.ollama_lanes', [])) as $i) {
            $keys[] = self::progressKey($locale, $i + 1);
        }

        $started = strtotime((string) ($status['started_at'] ?? '')) ?: null;
        $done = 0;
        $total = 0;
        $seen = false;

        foreach ($keys as $key) {
            $p = Cache::get($key);

            // Ignore a count left over from an EARLIER run of this language.
            if (! is_array($p) || ($started && strtotime((string) ($p['updated_at'] ?? '')) < $started)) {
                continue;
            }

            $seen = true;
            $done += (int) ($p['done'] ?? 0);
            $total += (int) ($p['total'] ?? 0);
        }

        if ($seen) {
            $status['progress'] = ['done' => $done, 'total' => $total];
        }

        return $status;
    }

    public function handle(ContentLocales $locales): void
    {
        $locale = $locales->normalise($this->locale);

        if ($locale === null || $locale === 'en') {
            return;
        }

        Cache::put(self::statusKey($locale), [
            'state' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'complete' => null,
            'tier' => $this->tier,
            'rewrite' => $this->rewrite,
        ], now()->addHours(3));

        try {
            $options = ['locale' => $locale, '--tier' => $this->tier];

            if ($this->rewrite) {
                $options['--rewrite'] = true;
            }

            $code = Artisan::call('translate:interface', $options);

            /*
             * ⚠️ The command's EXIT CODE is the verdict, not "did it throw".
             * Non-zero means strings are still English — that is the whole
             * point of the gate added on 2026-09-09 — and the screen has to be
             * able to say so rather than showing a green tick over a gap.
             */
            Cache::put(self::statusKey($locale), [
                'state' => 'done',
                'started_at' => null,
                'finished_at' => now()->toIso8601String(),
                'complete' => $code === 0,
                'tier' => $this->tier,
                'rewrite' => $this->rewrite,
            ], now()->addHours(3));
        } catch (\Throwable $e) {
            Log::error('translation.interface_job_failed', [
                'locale' => $locale,
                // The message only. A provider's response body can carry a key.
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            Cache::put(self::statusKey($locale), [
                'state' => 'failed',
                'started_at' => null,
                'finished_at' => now()->toIso8601String(),
                'complete' => false,
                'tier' => $this->tier,
                'rewrite' => $this->rewrite,
            ], now()->addHours(3));
        }
    }
}
