<?php

namespace App\Events\Support;

/**
 * WHICH ACTIVITY of an event a division belongs to — "Gi", "No-Gi", a kata, a
 * para category — read off the organiser's own division names.
 *
 * One event may hold several activities to compete in, each drawn and paid for
 * separately (owner's ruling, 2026-09-11), and an athlete may enter more than
 * one. A reader looking down an entry list wants that at a glance — "Gi",
 * "No-Gi", "Gi + No-Gi" — rather than having to compare two long division
 * names for the one bracketed word that differs.
 *
 * ⚠️ It is a READING of a NAME, not a column. The platform has no activity
 * model: organisers express the activity by suffixing the division with a
 * bracketed tag — "Group B (60+) [Gi]", "المجموعة ج (70+) [بدون زي]" — which is
 * the convention every event on the platform already follows. So:
 *
 *   · The tag is whatever is inside the LAST [...] of the name. It is never
 *     parsed, matched against a vocabulary or translated: the organiser's word
 *     is shown back to the reader, in whatever language they wrote it, which is
 *     also why this works for a sport nobody has thought of yet.
 *   · A division with no bracketed tag has no activity, and the label is null —
 *     the card then simply does not carry one. An event that runs a single
 *     activity says nothing, because there is nothing to tell apart.
 *
 * When the platform grows a real activity model, this class is the seam to
 * replace — everything else reads `activity` off the payload.
 */
class ActivityTag
{
    /** How many activities one label will name before it stops listing them. */
    private const MAX = 4;

    /**
     * The activity one division declares, or null.
     *
     * "Group E (Blue, Purple) (80+) [No-Gi]" → "No-Gi"
     * "Group A (60-)"                        → null
     */
    public static function of(?string $divisionName): ?string
    {
        if (! $divisionName) {
            return null;
        }

        // The LAST bracketed group: a division name may carry brackets of its
        // own ("(Blue, Purple)" is parenthesised, but "[…]" has been used for
        // the activity), and the activity is written at the end.
        if (! preg_match_all('/\[([^\[\]]{1,40})\]/u', $divisionName, $m)) {
            return null;
        }

        $tag = trim((string) end($m[1]));

        return $tag === '' ? null : $tag;
    }

    /**
     * The form two spellings of one activity have in common.
     *
     * Lower-cased, dashes/underscores/punctuation and runs of whitespace
     * collapsed away: "No-Gi", "No Gi" and "no gi" all fold to "nogi". Used to
     * compare tags, never to display one.
     */
    private static function fold(string $tag): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($tag)) ?: mb_strtolower($tag);
    }

    /**
     * The one line that says which activities these divisions cover.
     *
     * ["… [Gi]", "… [No-Gi]"] → "Gi + No-Gi"
     * ["… [Gi]", "… [Gi]"]    → "Gi"          (two weight groups, one activity)
     * ["Group A (60-)"]       → null
     *
     * Joined with " + " rather than a comma because that is how the entry fee
     * itself is sold — "Gi + No-Gi" is the option on the event's own price
     * list, so the badge and the receipt read the same.
     *
     * @param  array<int, string|null>  $divisionNames
     */
    public static function label(array $divisionNames): ?string
    {
        /*
         * Keyed by a NORMALISED tag, displayed as the organiser first wrote it.
         *
         * Real data spells one activity two ways — "[No-Gi]" on one group and
         * "[No Gi]" on another of the same event — and comparing the raw text
         * put both on one card ("No Gi + No-Gi"), which reads as two activities
         * that do not exist. Case, spacing and dashes are folded for the
         * COMPARISON only: nothing is translated, mapped to a vocabulary, or
         * rewritten on screen.
         */
        $tags = [];

        foreach ($divisionNames as $name) {
            $tag = self::of($name);

            if ($tag === null) {
                continue;
            }

            $key = self::fold($tag);

            if (! array_key_exists($key, $tags)) {
                $tags[$key] = $tag;
            }
        }

        $tags = array_values($tags);

        if ($tags === []) {
            return null;
        }

        // More activities than anybody puts on a card: say how many instead of
        // running the badge off the edge.
        if (count($tags) > self::MAX) {
            return implode(' + ', array_slice($tags, 0, self::MAX)).' +'.(count($tags) - self::MAX);
        }

        return implode(' + ', $tags);
    }
}
