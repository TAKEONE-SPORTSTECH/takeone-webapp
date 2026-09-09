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
    ) {}

    /** Where a screen looks to see whether this language is being worked on. */
    public static function statusKey(string $locale): string
    {
        return 'itr-run:'.$locale;
    }

    /** @return array{state: string, started_at: ?string, finished_at: ?string, complete: ?bool} */
    public static function status(string $locale): array
    {
        return (array) Cache::get(self::statusKey($locale), ['state' => 'idle']) + [
            'state' => 'idle', 'started_at' => null, 'finished_at' => null, 'complete' => null,
        ];
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
            ], now()->addHours(3));
        }
    }
}
