<?php

namespace App\Sports\Combat;

/**
 * A sport's own belts and grades — the shared SHAPE, filled in by each sport.
 *
 * Every combat sport ranks by belt and no two rank the same way: BJJ has kids'
 * two-tone belts and stripes, Karate has kyu and dan, Taekwondo has geup, poom
 * and dan. So the LADDER is private to the sport (declared in its own folder,
 * `beltLadder()` on the sport class, per CLAUDE.md *Shared stays shared,
 * private stays private*) and only the vocabulary for writing one down, and
 * the one door every screen asks through, live here.
 *
 * A rank:
 *   value   the colour key stored in `belt_colour` — `white`, `grey-white`, …
 *           Kept to colour words on purpose: every entry already on file is a
 *           colour key, so a ladder can only ever ADD values, never re-key one.
 *   label   what a reader sees
 *   bg/fg   the belt's colour and the ink that reads on it
 *   accent  the second colour of a two-tone belt (drawn as the stripe), or null
 *   group   'kids' | 'junior' | 'adult' | 'master' | null — which ladder it is on
 *   grades  the grades a belt of this colour carries (stripes, kyu, dan…), in
 *           order; offered where the grade is asked for
 *
 * Official ladders as of 2026-09-27 (owner: "use the official ones, I will
 * update them later"): IBJJF for BJJ, WKF kyu/dan for Karate, World Taekwondo /
 * Kukkiwon geup/poom/dan for Taekwondo. Changing one is an edit to that
 * sport's `beltLadder()`, nothing else.
 */
final class BeltLadder
{
    /** One palette for every sport, so "blue" is the same blue everywhere. */
    public const HEX = [
        'white' => '#f8fafc',
        'grey' => '#9ca3af',
        'yellow' => '#facc15',
        'orange' => '#fb923c',
        'green' => '#16a34a',
        'blue' => '#2563eb',
        'purple' => '#7c3aed',
        'brown' => '#78350f',
        'red' => '#dc2626',
        'black' => '#111827',
    ];

    /** Colours dark ink reads on; everything else takes white. */
    private const PALE = ['white', 'grey', 'yellow', 'orange'];

    /**
     * One rank. `$value` is `colour` or `colour-accent` (`grey-white`), and
     * both halves must be palette colours.
     *
     * @param  list<string>  $grades
     */
    public static function rank(string $value, ?string $group, array $grades = [], ?string $label = null): array
    {
        [$main, $accent] = array_pad(explode('-', $value, 2), 2, null);

        return [
            'value' => $value,
            'label' => $label ?? self::name($value),
            'bg' => self::HEX[$main] ?? '#9ca3af',
            'fg' => in_array($main, self::PALE, true) ? '#1f2937' : '#ffffff',
            'accent' => $accent !== null ? (self::HEX[$accent] ?? null) : null,
            'group' => $group,
            'grades' => array_values($grades),
        ];
    }

    /** "Grey / White" from `grey-white`, in the reader's language. */
    public static function name(string $value): string
    {
        return collect(explode('-', $value))
            ->map(fn (string $c) => __('personal.belt_'.$c))
            ->implode(' / ');
    }

    /**
     * Numbered grades — `:n stripes`, `:n kyu` — from a lang key that takes
     * `:n` (and may be a trans_choice).
     *
     * @return list<string>
     */
    public static function numbered(string $key, iterable $numbers): array
    {
        $out = [];

        foreach ($numbers as $n) {
            $out[] = trans_choice($key, $n, ['n' => $n]);
        }

        return $out;
    }

    /**
     * The ladder a sport declares, or [] when it declares none (or the sport
     * is not a combat sport at all). Never throws.
     *
     * @return list<array<string, mixed>>
     */
    public static function forSport(?string $sport): array
    {
        if (! $sport) {
            return [];
        }

        try {
            $plugin = app(SportRegistry::class)->get($sport);
        } catch (\Throwable) {
            return [];
        }

        if (! $plugin || ! method_exists($plugin, 'beltLadder')) {
            return [];
        }

        return array_values((array) $plugin->beltLadder());
    }

    /**
     * Every rank of every sport — for DRAWING a belt that is already on file,
     * where the sport is not to hand (a card, a chip). First sport wins on a
     * shared value, which is safe: a shared value is the same colour.
     *
     * @return list<array<string, mixed>>
     */
    public static function everySport(): array
    {
        static $all = null;

        if ($all !== null) {
            return $all;
        }

        $all = [];

        try {
            foreach (array_keys(config('combat.sports', [])) as $key) {
                foreach (self::forSport($key) as $rank) {
                    $all[$rank['value']] ??= $rank;
                }
            }
        } catch (\Throwable) {
            // no sports configured — nothing to add
        }

        return $all = array_values($all);
    }
}
