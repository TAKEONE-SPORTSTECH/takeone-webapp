<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * karate-shotukan-weightclasses — the federation's Karate (Shotokan) age
 * categories and kumite weight classes, season 2025-2026, for PHP.
 *
 * The TABLE is public/libraries/karate-shotukan-weightclasses/weightclasses.json
 * and nothing else: this class and the page library
 * (karate-shotukan-weightclasses.js, same folder) both read that one file, so
 * a class changed there changes everywhere at once. See the folder's README.
 *
 * Not the platform's own WKF table (app/Events/config/karate_divisions.php):
 * that is a different rulebook, used by the platform's karate events.
 */
final class KarateShotukanWeightclasses
{
    public const NAME = 'karate-shotukan-weightclasses';

    private static ?array $data = null;

    public static function path(): string
    {
        return public_path('libraries/'.self::NAME.'/weightclasses.json');
    }

    /** @return array{name:string, season:string, minAge:int, ageCategories:array, weights:array} */
    public static function data(): array
    {
        return self::$data ??= json_decode((string) file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * This table as a Karate category system (App\Sports\Combat\CategorySystem),
     * so an event can run under it and every classifier on the platform reads
     * the SAME file the invitation's library reads. Null if the file is missing.
     */
    public static function categorySystem(): ?\App\Sports\Combat\CategorySystem
    {
        try {
            $d = self::data();
        } catch (\Throwable) {
            return null;
        }

        $groups = [];
        $min = (int) ($d['minAge'] ?? 0);
        foreach ($d['ageCategories'] as $c) {
            // The rule book's English name ("Under 12") is what an event's
            // divisions are called; the French name stays the table's own key.
            $groups[] = ['key' => (string) ($c['label'] ?? $c['name']), 'min' => $min, 'max' => $c['maxAge'] === null ? null : (int) $c['maxAge']];
            $min = $c['maxAge'] === null ? $min : (int) $c['maxAge'] + 1;
        }

        $classes = [];
        foreach (['M' => 'male', 'F' => 'female'] as $code => $gender) {
            foreach ((array) ($d['weights'][$code] ?? []) as $group => $list) {
                $prev = 0.0;
                foreach ((array) $list as $w) {
                    $kg = (float) substr((string) $w, 1);
                    $classes[self::englishName((string) $group)][$gender][] = $w[0] === '+'
                        ? ['label' => (string) $w, 'min' => $kg, 'max' => 200]
                        : ['label' => (string) $w, 'min' => 0, 'max' => $kg];
                    $prev = $kg;
                }
            }
        }

        return new \App\Sports\Combat\CategorySystem(
            key: 'shotokan',
            label: 'Shotokan',
            ageGroups: $groups,
            classes: $classes,
            note: (string) ($d['season'] ?? ''),
        );
    }

    public static function season(): string
    {
        return (string) self::data()['season'];
    }

    /** @return array<int, string> */
    /** The English name of an age category ("Benjamins" → "Under 12"); unknown names come back as given. */
    public static function englishName(string $category): string
    {
        foreach (self::data()['ageCategories'] as $c) {
            if ($c['name'] === $category) {
                return (string) ($c['label'] ?? $c['name']);
            }
        }

        return $category;
    }

    public static function ageCategories(): array
    {
        return array_column(self::data()['ageCategories'], 'name');
    }

    public static function ageCategoryOf(int $age): ?string
    {
        if ($age < (int) self::data()['minAge']) {
            return null;
        }
        foreach (self::data()['ageCategories'] as $c) {
            if ($c['maxAge'] === null || $age <= $c['maxAge']) {
                return $c['name'];
            }
        }

        return null;
    }

    /** @return array<int, string> e.g. ['-52', '-57', '-63', '-70', '+70'] */
    public static function weights(string $gender, string $category): array
    {
        return self::data()['weights'][self::g($gender)][$category] ?? [];
    }

    /** '-X' holds up to and including X; above the last '-X' is the '+' class. Null without a weight. */
    public static function weightClassOf(string $gender, string $category, ?float $kg): ?string
    {
        $list = self::weights($gender, $category);
        if ($list === [] || ! $kg || $kg <= 0) {
            return null;
        }
        foreach ($list as $w) {
            if ($w[0] === '-' && $kg <= (float) substr($w, 1)) {
                return $w;
            }
        }

        return end($list);
    }

    public static function label(string $gender, string $category, ?float $kg): string
    {
        $w = self::weightClassOf($gender, $category, $kg);

        return $w ? 'Kumite '.$w.' kg' : 'Kumite — TBC';
    }

    /**
     * @param  CarbonInterface|string|null  $birthdate  or pass $age
     * @return array{ageGroup:string, weightClass:string, label:string}|null
     */
    public static function classify(string $gender, CarbonInterface|string|null $birthdate, ?float $weight, ?int $age = null, CarbonInterface|string|null $at = null): ?array
    {
        if ($gender === '' || ! $weight) {
            return null;
        }
        if ($age === null && $birthdate) {
            $age = Carbon::parse($birthdate)->diffInYears($at ? Carbon::parse($at) : now(), true);
            $age = (int) floor($age);
        }
        $group = $age === null ? null : self::ageCategoryOf($age);
        $w = $group ? self::weightClassOf($gender, $group, $weight) : null;

        return $w ? ['ageGroup' => $group, 'weightClass' => $w, 'label' => 'Kumite '.$w.' kg'] : null;
    }

    /**
     * The page library with its table embedded — drop into any <head>. The table
     * is inlined so every JS call is synchronous; the script is versioned by the
     * table's own modification time.
     */
    public static function tags(): string
    {
        $json = json_encode(self::data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $v = substr(md5((string) @filemtime(self::path()).'|'.(string) @filemtime(public_path('libraries/'.self::NAME.'/'.self::NAME.'.js'))), 0, 8);

        return '<script>window.__KARATE_SHOTUKAN_WEIGHTCLASSES__ = '.$json.';</script>'."\n"
            .'<script src="/libraries/'.self::NAME.'/'.self::NAME.'.js?v='.$v.'"></script>';
    }

    private static function g(string $gender): string
    {
        return preg_match('/^f/i', $gender) ? 'F' : 'M';
    }
}
