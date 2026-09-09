<?php

namespace App\Translation\Services;

/**
 * Every interface string that fell back to English, this request.
 *
 * ── Why a counter and not just a log line ────────────────────────────────────
 *
 * Laravel's fallback is silent by design: a key with no entry in the active
 * locale resolves from `lang/en` and the page renders, which is the right
 * behaviour and the reason nothing ever breaks. It is also the reason nobody
 * knew the Chinese interface was 2.7% translated until somebody read a page and
 * counted by hand.
 *
 * So the fallback stays — a page in the wrong language beats a page with holes
 * in it (RULE #1) — and it stops being invisible. One aggregated line per
 * request, never one per string: a poster falling back three hundred times would
 * otherwise write three hundred log lines and the log would be the outage.
 *
 * In-memory and per-request. Nothing is persisted here; `translate:parity` is
 * the tool for a considered measurement, and this is the smoke alarm.
 */
class MissingStrings
{
    /** @var array<string, array<string, int>> locale => key => times asked */
    private array $seen = [];

    private int $total = 0;

    public function record(string $locale, string $key): void
    {
        $this->seen[$locale][$key] = ($this->seen[$locale][$key] ?? 0) + 1;
        $this->total++;
    }

    public function total(): int
    {
        return $this->total;
    }

    /** @return array<string, array<string, int>> */
    public function all(): array
    {
        return $this->seen;
    }

    /** @return array<int, string> The keys asked for most often, worst first. */
    public function worst(string $locale, int $limit = 25): array
    {
        $keys = $this->seen[$locale] ?? [];
        arsort($keys);

        return array_slice(array_keys($keys), 0, $limit);
    }

    public function reset(): void
    {
        $this->seen = [];
        $this->total = 0;
    }
}
