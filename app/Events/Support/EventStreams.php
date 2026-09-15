<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventFeeOption;
use App\Models\EventRegistrationFeeLine;

/**
 * The parts of an event somebody enters separately — and who is in which.
 *
 * An event may run several streams: Gi and No-Gi, Kata and Kumite, Poomsae and
 * Sparring. They are entered and paid for apart, an athlete may be in one or
 * both, and a division belongs to exactly one. Nothing here knows what any of
 * those words mean: the organiser names them and this only carries them around.
 *
 * ── Why it is a column now and not a regex ──────────────────────────────────
 * It used to be read out of TEXT, twice and differently: the bracketed tail of
 * a division's name, and the bracketed part of a fee option's label. Two free
 * strings, two spellings of the same thing ("No-Gi" / "No Gi"), and a combined
 * option that is not equal to anything — "Gi + No-Gi" is a SET. So the roster
 * could not answer "is this person even entered in Gi", and the entrant picker
 * offered them anyway.
 *
 * With the streams declared once and pointed at from both sides, the question
 * is set membership and nothing is parsed at read time.
 *
 * ── The old readings still work ─────────────────────────────────────────────
 * Every method falls back to the tag readers (ActivityTag, EventFee::optionTag)
 * when a row has not been backfilled. An event that never declares a stream
 * behaves exactly as it did, and a half-migrated one is never wrong — only less
 * certain.
 */
class EventStreams
{
    /**
     * The event's own vocabulary: [['key' => 'gi', 'label' => 'Gi'], …].
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function of(ClubEvent $event): array
    {
        $rows = [];

        foreach ((array) ($event->streams ?? []) as $row) {
            $key = self::key(is_array($row) ? ($row['key'] ?? $row['label'] ?? '') : (string) $row);
            $label = trim((string) (is_array($row) ? ($row['label'] ?? $row['key'] ?? '') : $row));

            if ($key !== '' && $label !== '') {
                $rows[$key] = ['key' => $key, 'label' => $label];
            }
        }

        return array_values($rows);
    }

    /** Does this event run more than one? One stream is nothing to tell apart. */
    public static function many(ClubEvent $event): bool
    {
        return count(self::of($event)) > 1;
    }

    /**
     * The comparison form of a stream name.
     *
     * Case, spacing and dashes folded away, so "No-Gi", "No Gi" and "no gi" are
     * one stream. Used to COMPARE and to key, never to display — the organiser's
     * own spelling is what a reader sees.
     */
    public static function key(string $name): string
    {
        $folded = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($name)));

        return (string) ($folded ?: '');
    }

    /**
     * Which stream a division is in — the column, else its name's old tag.
     *
     * ⚠️ The fallback is only trusted when the event actually DECLARES that
     * stream. Bracketed tails are used for all sorts of things ("Adults
     * (Open)", "Black belt (invitational)"), and a tag read as a stream nobody
     * sells matches nothing anybody bought — which would read as 'out' for the
     * entire roster and empty the picker. An undeclared tag is therefore no
     * stream at all, and the division goes on offering everybody.
     */
    public static function ofDivision(EventCategory $category): ?string
    {
        if ($category->stream) {
            return self::key((string) $category->stream);
        }

        $tag = ActivityTag::of($category->name);
        $key = $tag ? self::key($tag) : null;

        if ($key === null || $key === '') {
            return null;
        }

        $event = $category->relationLoaded('event') ? $category->event : ClubEvent::find($category->event_id);

        return ($event && in_array($key, array_column(self::of($event), 'key'), true)) ? $key : null;
    }

    /**
     * What buying an option entitles you to — the column, else its label's tag,
     * which is split on "+" so a combined "Gi + No-Gi" grants both.
     *
     * @return array<int, string>
     */
    public static function grantedByOption(EventFeeOption $option): array
    {
        if (is_array($option->grants) && $option->grants) {
            return array_values(array_filter(array_map([self::class, 'key'], $option->grants)));
        }

        return self::split(EventFee::optionTag((string) $option->label));
    }

    /**
     * Every stream an ENTRY has bought, by registration id.
     *
     * Read from the fee lines, which record what was chosen when the entry was
     * taken: the option id where one was kept, and the label it was sold under
     * otherwise. An entry with no option lines has bought nothing in
     * particular, which is an empty list and NOT a refusal — see judge().
     *
     * @param  iterable<int>  $registrationIds
     * @return array<int, array<int, string>>
     */
    public static function grantedForMany(iterable $registrationIds): array
    {
        $ids = collect($registrationIds)->map('intval')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $options = EventFeeOption::whereIn('id', EventRegistrationFeeLine::whereIn('registration_id', $ids)
            ->whereNotNull('fee_option_id')->distinct()->pluck('fee_option_id'))
            ->get()->keyBy('id');

        $out = [];

        foreach (EventRegistrationFeeLine::whereIn('registration_id', $ids)
            ->where('kind', EventRegistrationFeeLine::KIND_OPTION)
            ->get(['registration_id', 'fee_option_id', 'label']) as $line) {

            $keys = ($line->fee_option_id && $options->has($line->fee_option_id))
                ? self::grantedByOption($options[$line->fee_option_id])
                : self::split(EventFee::optionTag((string) $line->label));

            $reg = (int) $line->registration_id;
            $out[$reg] = array_values(array_unique(array_merge($out[$reg] ?? [], $keys)));
        }

        return $out;
    }

    /**
     * "Gi + No-Gi" → ['Gi', 'No-Gi'] — the organiser's own spellings, in order.
     *
     * ⚠️ Delimited with `~`, not `/`: the separator class contains a slash
     * ("Kata/Kumite" is how some organisers write it), and inside a `/…/`
     * pattern that slash ends the expression — PHP then reads the rest as
     * modifiers and throws "Unknown modifier".
     */
    public static function parts(string $tag): array
    {
        $parts = preg_split('~\s*[+＋/،,&]\s*|\s+and\s+|\s+و\s+~iu', $tag) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /** The same split, as comparison keys. A combined option is a SET. */
    public static function split(string $tag): array
    {
        return array_values(array_filter(array_map([self::class, 'key'], self::parts($tag))));
    }

    /**
     * Read the streams an event already expresses in its text, and write them
     * into the columns. Repeatable, and it never overwrites a value somebody
     * has set by hand.
     *
     * @return array{streams: int, options: int, divisions: int}
     */
    public static function backfill(ClubEvent $event): array
    {
        $labels = [];   // key => the organiser's own spelling, first one wins

        $remember = function (string $tag) use (&$labels) {
            foreach (self::parts($tag) as $part) {
                $key = self::key($part);

                if ($key !== '' && ! isset($labels[$key])) {
                    $labels[$key] = $part;
                }
            }
        };

        $options = EventFeeOption::where('event_id', $event->id)->get();

        foreach ($options as $option) {
            $remember(EventFee::optionTag((string) $option->label));
        }

        $divisions = EventCategory::where('event_id', $event->id)->get();

        foreach ($divisions as $division) {
            if ($tag = ActivityTag::of($division->name)) {
                $remember($tag);
            }
        }

        if (! $labels) {
            return ['streams' => 0, 'options' => 0, 'divisions' => 0];
        }

        // The vocabulary, only where the organiser has not written one.
        if (! self::of($event)) {
            $event->streams = array_values(array_map(
                fn ($label, $key) => ['key' => $key, 'label' => $label],
                $labels, array_keys($labels)
            ));
            $event->save();
        }

        $known = array_column(self::of($event), 'key');
        $touchedOptions = 0;
        $touchedDivisions = 0;

        foreach ($options as $option) {
            if (is_array($option->grants) && $option->grants) {
                continue;
            }

            $keys = array_values(array_intersect(self::split(EventFee::optionTag((string) $option->label)), $known));

            if ($keys) {
                $option->grants = $keys;
                $option->save();
                $touchedOptions++;
            }
        }

        foreach ($divisions as $division) {
            if ($division->stream || $division->isHeading()) {
                continue;
            }

            $tag = ActivityTag::of($division->name);
            $key = $tag ? self::key($tag) : null;

            if ($key && in_array($key, $known, true)) {
                $division->stream = $key;
                $division->save();
                $touchedDivisions++;
            }
        }

        return ['streams' => count($known), 'options' => $touchedOptions, 'divisions' => $touchedDivisions];
    }

    /**
     * How an entry sits against a division's stream.
     *
     * 'in'      — they bought it
     * 'out'     — the division is in a stream, and what they bought is not
     * 'unknown' — nothing recorded either side, which is most events: one
     *             stream, no tags, nothing to tell apart. Never a refusal.
     *
     * @param  array<int, string>  $granted
     */
    public static function judge(?string $divisionStream, array $granted): string
    {
        if ($divisionStream === null || ! $granted) {
            return 'unknown';
        }

        return in_array($divisionStream, $granted, true) ? 'in' : 'out';
    }
}
