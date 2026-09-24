<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The country list, and the one place a code becomes a name.
 *
 * `tenants.country` stores an ISO-3166 code — 'BH', not 'Bahrain' — because the
 * public club URL is built from it. That is right for a URL and wrong for a wall
 * screen: a hall reads "BAHRAIN", not "BH", and so does a competitor's family
 * watching from the seats. Every screen that announces where somebody is from
 * resolves the code through here.
 *
 * Read from public/data/countries.json, which is the list every country
 * dropdown in the product already uses, so a name shown on a mat and a name
 * shown in an admin form can never disagree.
 */
final class Countries
{
    /** Resolved once per request on top of the day-long cache. */
    private static ?array $byCode = null;

    /**
     * Every country as a picker wants them: `[['code' => 'bh', 'name' => 'Bahrain'], …]`,
     * sorted by name.
     */
    public static function all(): array
    {
        return Cache::remember('countries.list', now()->addDay(), function () {
            return collect(self::rows())
                ->map(fn ($c) => [
                    'code' => strtolower((string) ($c['iso2'] ?? '')),
                    'name' => trim((string) ($c['name'] ?? '')),
                ])
                ->filter(fn ($c) => preg_match('/^[a-z]{2}$/', $c['code']) && $c['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    /**
     * Every country a phone number can come from, as a dial-code picker wants
     * them: `[['code' => '+973', 'name' => 'Bahrain', 'flag' => 'bh'], …]`,
     * sorted by name.
     *
     * ⚠️ Read from the SAME list as everything else. `<x-fields.phone-code>`
     * used to carry a hand-written array of sixty countries inside its x-data,
     * which is how the platform came to have no Syria (+963), no Yemen, no
     * Sudan, no Afghanistan and no Libya — a member from any of them could not
     * enter their own phone number. A list typed by hand is a list that is
     * wrong the day after it is typed.
     *
     * @return list<array{code: string, name: string, flag: string}>
     */
    public static function dialCodes(): array
    {
        return Cache::remember('countries.dial_codes', now()->addDay(), function () {
            return collect(self::rows())
                ->map(fn ($c) => [
                    'code' => trim((string) ($c['call_code'] ?? '')),
                    'name' => trim((string) ($c['name'] ?? '')),
                    'flag' => strtolower(trim((string) ($c['iso2'] ?? ''))),
                ])
                ->filter(fn ($c) => $c['code'] !== '' && $c['name'] !== '' && preg_match('/^[a-z]{2}$/', $c['flag']))
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    /**
     * The full name for an ISO code, or null when we do not have one.
     *
     * Accepts alpha-2 or alpha-3, in any case. Null rather than a guess: a code
     * this list does not know is not turned into an invented country.
     */
    public static function name(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        return self::index()[$code] ?? null;
    }

    /**
     * What a screen should print for a stored country value.
     *
     * The same field arrives as either a code or a name depending on where it
     * came from — a club's `country` column holds 'BH', while an official who
     * picked a country at the mat stored 'Bahrain'. A code becomes its name; a
     * name is already right and is returned untouched; anything we cannot place
     * is passed through as given rather than blanked, because a value an
     * organiser typed is still the best answer we have.
     */
    public static function label(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // Only a bare code is a lookup. 'Chad' is four letters and a country.
        if (preg_match('/^[A-Za-z]{2,3}$/', $value)) {
            return self::name($value) ?? strtoupper($value);
        }

        return $value;
    }

    /**
     * The ISO 3166-1 alpha-3 code for a country, e.g. BH -> BHR.
     *
     * Storage groups clubs by country, and a three-letter code is the one form
     * that is unambiguous at a glance on a NAS listing — `BH` reads as an
     * abbreviation of something, `BHR` reads as a country.
     *
     * Falls back to `UNK` rather than an empty segment: a club with no country
     * still needs somewhere to live, and a path must never collapse to
     * `clubs//my-club`.
     */
    public static function iso3(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return 'UNK';
        }

        if (strlen($code) === 3 && isset(self::index()[$code])) {
            return $code;
        }

        foreach (self::rows() as $row) {
            if (strtoupper(trim((string) ($row['iso2'] ?? ''))) === $code) {
                $iso3 = strtoupper(trim((string) ($row['iso3'] ?? '')));

                if ($iso3 !== '') {
                    return $iso3;
                }
            }
        }

        return 'UNK';
    }

    /**
     * The alpha-2 a flag file is named by, from whatever the row stores.
     *
     * The mirror of iso3(): members carry 'BH', some clubs carry 'BHR', and the
     * flag set is named in alpha-2. Null rather than a guess when nothing
     * matches — a flag drawn from a guessed country is worse than no flag, and
     * the component says the code instead.
     */
    public static function iso2(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        if (strlen($code) === 2) {
            return isset(self::index()[$code]) ? strtolower($code) : null;
        }

        foreach (self::rows() as $row) {
            if (strtoupper(trim((string) ($row['iso3'] ?? ''))) === $code) {
                $iso2 = strtoupper(trim((string) ($row['iso2'] ?? '')));

                if ($iso2 !== '') {
                    return strtolower($iso2);
                }
            }
        }

        // A NAME, last. The mirror of label(), which already accepts both: a
        // club's `country` column holds 'BH', but an organiser typing a team
        // into an event writes 'Bahrain', and a screen that can print the word
        // but not the flag beside it is reading the same field two ways.
        //
        // Last on purpose — a two- or three-letter code is never guessed at
        // from this list, so 'CHAD' stays the country and not a near-miss.
        foreach (self::rows() as $row) {
            if (strtoupper(trim((string) ($row['name'] ?? ''))) === $code) {
                $iso2 = strtoupper(trim((string) ($row['iso2'] ?? '')));

                if ($iso2 !== '') {
                    return strtolower($iso2);
                }
            }
        }

        return null;
    }

    /** code (upper, alpha-2 AND alpha-3) => name */
    private static function index(): array
    {
        if (self::$byCode !== null) {
            return self::$byCode;
        }

        self::$byCode = Cache::remember('countries.by_code', now()->addDay(), function () {
            $map = [];

            foreach (self::rows() as $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                foreach (['iso2', 'iso3'] as $key) {
                    $code = strtoupper(trim((string) ($row[$key] ?? '')));

                    if ($code !== '') {
                        $map[$code] = $name;
                    }
                }
            }

            return $map;
        });

        return self::$byCode;
    }

    /** The raw file. A missing or unreadable list is empty, never an error. */
    private static function rows(): array
    {
        $path = public_path('data/countries.json');

        if (! is_readable($path)) {
            return [];
        }

        $rows = json_decode((string) file_get_contents($path), true);

        return is_array($rows) ? $rows : [];
    }
}
