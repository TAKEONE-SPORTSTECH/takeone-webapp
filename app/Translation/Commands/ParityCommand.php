<?php

namespace App\Translation\Commands;

use App\Translation\Services\ContentLocales;
use App\Translation\Services\ParityAudit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Walk real pages in real languages and count what is still English.
 *
 * The instrument behind the acceptance bar. `translate:interface --check`
 * validates FILES; this renders PAGES, which is the only thing that can catch a
 * string that never went through `__()` at all, a payload that resolved
 * content in one place and not another, or a locale whose files exist but whose
 * middleware never applied them.
 *
 *   php artisan translate:parity /e/<uuid> --locales=en,ar,zh,ja,fr,hi
 *   php artisan translate:parity /e/<uuid> --show=20        list what leaked
 *   php artisan translate:parity /e/<uuid> --literals       the second pass
 *
 * Read-only: it issues GET requests through the app's own HTTP kernel and
 * writes nothing.
 */
class ParityCommand extends Command
{
    protected $signature = 'translate:parity
        {path* : one or more URL paths to render, e.g. /e/<uuid> /e/<uuid>/enter}
        {--locales=en,ar,zh,ja,fr,hi : which languages to render each path in}
        {--show=0 : list this many leaked strings per page}
        {--literals : also report Latin-script runs on non-Latin pages}';

    protected $description = 'Count how much of a rendered page falls back to English, per locale';

    public function handle(ParityAudit $audit, ContentLocales $locales, Kernel $kernel): int
    {
        $paths = (array) $this->argument('path');
        $wanted = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('locales')))));
        $show = (int) $this->option('show');

        $rows = [];
        $worst = 0;

        foreach ($paths as $path) {
            foreach ($wanted as $locale) {
                if ($locale !== 'en' && $locales->normalise($locale) === null) {
                    $this->warn("  skipped {$locale}: not a language this platform serves");

                    continue;
                }

                [$status, $html] = $this->render($kernel, $path, $locale);

                if ($status >= 400 || $html === '') {
                    $rows[] = [$path, $locale, $status, '—', '—', '—', '—'];

                    continue;
                }

                $report = $audit->report($html, $locale, $this->namesOn($path));
                $leaked = $locale === 'en' ? [] : $report['leaked'];
                $fell = $locale === 'en' ? 0 : $report['fell_back'];
                $rendered = $report['resolved'] + $fell;

                $rows[] = [
                    $path,
                    $locale,
                    $status,
                    number_format($rendered),
                    number_format($report['resolved']),
                    $fell === 0 ? '0' : (string) $fell,
                    $rendered ? sprintf('%.1f%%', 100 * $report['resolved'] / $rendered) : '—',
                ];

                $worst = max($worst, $locale === 'en' ? 0 : $fell);

                if ($show > 0 && $leaked !== []) {
                    $this->line("\n  <options=bold>{$locale}</> — {$fell} English strings on {$path}:");

                    foreach (array_slice($leaked, 0, $show, true) as $key => $english) {
                        $this->line(sprintf('    %-46s %s', $key, mb_substr($english, 0, 60)));
                    }
                }

                if ($this->option('literals')) {
                    $lit = $audit->literals($html, $locale);

                    if ($lit !== []) {
                        $this->line("\n  <options=bold>{$locale}</> — Latin runs not in any lang file ("
                            .count($lit).'), first 15:');

                        foreach (array_slice($lit, 0, 15) as $one) {
                            $this->line('    '.mb_substr($one, 0, 70));
                        }
                    }
                }
            }
        }

        $this->line('');
        $this->table(['path', 'locale', 'http', 'rendered', 'resolved', 'fell back', 'ok'], $rows);

        // Non-zero when anything fell back, so this is usable as a gate.
        return $worst === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The proper nouns on this page — the words a translator is right to leave
     * alone.
     *
     * Taken from the record itself, and from the same `keep` list the
     * translation agent is given, so the audit and the translator agree about
     * what is a name. Without it a club called "Victory Academy" makes every
     * page it hosts look like it leaked the English word "Victory".
     *
     * Silent and empty for a path that is not an event: a name list is a
     * refinement, and a missing one only ever costs precision.
     *
     * @return array<int, string>
     */
    private function namesOn(string $path): array
    {
        if (! preg_match('#^/e/([0-9a-f-]{36})#i', $path, $m)) {
            return [];
        }

        $event = \App\Models\ClubEvent::query()->where('uuid', strtolower($m[1]))->first();

        if ($event === null) {
            return [];
        }

        return \App\Translation\Translations::source(function () use ($event) {
            $context = $event->translationContext();

            return array_filter(array_merge(
                (array) ($context['keep'] ?? []),
                [$event->title, $event->tenant?->club_name, $event->location],
            ));
        });
    }

    /**
     * Render one path as a guest reading in one language.
     *
     * The locale is asked for with `Accept-Language`, not by setting it here:
     * that puts it through App\Http\Middleware\SetLocale, so the audit measures
     * the real resolution chain rather than a state the command arranged. A
     * locale the middleware refuses therefore shows up as English, which is
     * exactly the finding.
     *
     * @return array{0: int, 1: string}
     */
    private function render(Kernel $kernel, string $path, string $locale): array
    {
        $request = Request::create($path, 'GET');
        $request->headers->set('Accept-Language', $locale);

        try {
            $response = $kernel->handle($request);
        } catch (\Throwable $e) {
            $this->warn("  {$path} [{$locale}] threw: ".$e->getMessage());

            return [500, ''];
        }

        return [$response->getStatusCode(), (string) $response->getContent()];
    }
}
