<?php

namespace App\Http\Middleware;

use App\Translation\Translations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * One line per request saying how much of the page was still English.
 *
 * The point of the whole instrument: "no silent fallback". Laravel resolving a
 * missing Japanese key from `lang/en` is the correct behaviour and stays — but
 * a page doing it three hundred times is a defect, and until this existed the
 * only way to find out was for a person to read the page.
 *
 * ── Why it logs once, after the response ─────────────────────────────────────
 *
 * `terminate()` runs after the visitor has their page, so measuring costs them
 * nothing. And it is ONE aggregated line carrying the count and the worst
 * offenders — never a line per string, which on a poster would mean three
 * hundred log writes and a log that is itself the outage.
 *
 * The keys are lang keys — `events.public_about`, never a person's data — so
 * this can log freely without touching the rule about what may go in a log.
 *
 * Reads the count through the translation module's front door: the counter
 * itself is that module's private business (tests/Feature/Modules/
 * ModuleBoundaryTest).
 */
class ReportUntranslated
{
    /** Below this, a page is unremarkable and not worth a line. */
    private const NOISE_FLOOR = 3;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $report = Translations::untranslated();

            if ($report['total'] < self::NOISE_FLOOR) {
                return;
            }

            foreach ($report['locales'] as $locale => $row) {
                Log::warning('translation.interface_fell_back', [
                    'locale' => $locale,
                    'path' => $request->path(),
                    'strings' => $row['strings'],
                    'worst' => $row['worst'],
                ]);
            }
        } catch (\Throwable $e) {
            // A diagnostic that can break a request is worse than no
            // diagnostic. Nothing here is allowed to matter.
        }
    }
}
