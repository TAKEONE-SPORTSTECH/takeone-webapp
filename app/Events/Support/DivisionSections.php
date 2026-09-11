<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventCategory;

/**
 * The organiser's list of divisions, cut into the sections their headings mean.
 *
 * A heading (`event_categories.is_heading`) is a caption over the divisions
 * BELOW it, until the next heading — "GI", then five weight classes, then
 * "NO-GI", then five more. That is a statement about ORDER, so the only thing
 * that can read it is something walking the list in `sort_order`. This is that
 * one thing.
 *
 * ── Why this exists as a class ─────────────────────────────────────────────
 * Both `App\Events\Support\PublicEvent::payload()` and
 * `PersonalEventController::eventView()` were building `divisions` and
 * `divisions_source` with the same two queries, and both fed the SAME two
 * partials (`partials/event-detail-card-{mobile,desktop}`). A second copy of
 * the sectioning would have been a third place for the same rule to drift in
 * (CLAUDE.md → Shared Stays Shared), so both now call this.
 *
 * ── What it guarantees to callers ──────────────────────────────────────────
 *  · `divisions` and `divisions_source` contain NO headings. Every reader that
 *    counts or lists them — the podium sheet, "podium decided X of Y", the
 *    draw count, the 404 guard on the public draw section — was counting
 *    headings as divisions, so filtering here fixes all of them at once.
 *  · `sections` is the same list with its structure kept: each entry is a
 *    heading (or null, for the divisions that come BEFORE the first heading)
 *    and the divisions under it.
 *  · A heading with nothing under it is dropped. It is an unfinished thought
 *    of the organiser's, and a caption over an empty space reads as a fault on
 *    a page a stranger is looking at.
 *
 * Names come through the event's translation document, keyed by the division's
 * own id so a reordered list keeps each translation attached to its division.
 * `*_source` carries the untranslated name because the partials group by
 * parsing "{Age} {Men|Women} {weight}" out of the shape
 * AbstractCombatSport::divisionName() writes — see PublicEvent::payload().
 */
class DivisionSections
{
    /**
     * @param  object  $tr  the event's translation reader (App\Translation\Translations::of)
     * @return array{
     *     divisions: list<string>,
     *     divisions_source: list<string>,
     *     sections: list<array{
     *         heading: ?string,
     *         items: list<string>,
     *         items_source: list<string>,
     *         groups: list<array{label: string, female: bool, fallback: bool, items: list<string>}>
     *     }>
     * }
     */
    public function build(ClubEvent $event, object $tr): array
    {
        $rows = $event->categories()->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'is_heading']);

        $divisions = [];
        $source = [];
        $sections = [];

        // The run of divisions before the first heading. Kept with a null
        // heading rather than dropped: an event that never used a heading is
        // ALL of this, and it must render exactly as it always has.
        $open = ['heading' => null, 'items' => [], 'items_source' => []];

        foreach ($rows as $c) {
            $name = (string) $tr->get('divisions.'.$c->id, (string) $c->name);

            if ($this->isHeading($c)) {
                $sections[] = $open;
                $open = ['heading' => $name, 'items' => [], 'items_source' => []];

                continue;
            }

            $divisions[] = $name;
            $source[] = (string) $c->name;

            $open['items'][] = $name;
            $open['items_source'][] = (string) $c->name;
        }

        $sections[] = $open;

        // Each section's chips, grouped the way the detail card has always
        // grouped them (see genderGroups).
        $sections = array_map(function (array $sec) {
            $sec['groups'] = $this->genderGroups($sec['items'], $sec['items_source']);

            return $sec;
        }, $sections);

        return [
            'divisions' => $divisions,
            'divisions_source' => $source,
            // An empty section says nothing and is not drawn. The leading one
            // is dropped by the same rule, so an event whose list opens with a
            // heading does not start with a blank.
            'sections' => array_values(array_filter(
                $sections,
                fn (array $s) => $s['items'] !== []
            )),
        ];
    }

    /**
     * `is_heading` may not be in the selected columns on every caller, so this
     * asks the model rather than the attribute — EventCategory::isHeading() is
     * the one definition of what a heading is.
     */
    private function isHeading(EventCategory $c): bool
    {
        return $c->isHeading();
    }

    /**
     * The chips of one section, grouped by the gender in their names.
     *
     * Lifted out of `partials/event-detail-card-mobile` and its desktop twin,
     * which each carried a copy of this parse — the mobile one had already
     * grown a fix the desktop one had not.
     *
     * ⚠️ Groups on the SOURCE name and shows the TRANSLATED one. The parse
     * looks for the English "Men"/"Women" that
     * AbstractCombatSport::divisionName() writes; run against a translated
     * name it never matches, and every division collapses into one unlabelled
     * group — so the gender grouping silently disappeared in every language
     * but English the moment divisions started being translated.
     *
     * The trade, stated: in English nothing changes — the caption is still
     * "Cadet Men". In another language it is the translated GENDER alone
     * ("Homens") and the full translated name carries the rest. The age word
     * is dropped rather than shown in English, because "Cadet Homens" is worse
     * than either language on its own.
     *
     * `fallback` marks the group that matched nothing. Its label is the word
     * "Divisions" — the section's own title — which is why the caller does not
     * draw it under a heading that already says what the section is.
     *
     * @param  list<string>  $items       translated names
     * @param  list<string>  $source      the same names, untranslated
     * @return list<array{label: string, female: bool, fallback: bool, items: list<string>}>
     */
    private function genderGroups(array $items, array $source): array
    {
        $groups = [];

        foreach (array_values($items) as $i => $name) {
            $src = $source[$i] ?? $name;
            $translated = $src !== $name;

            if (preg_match('/^(.*?)\s*\b(Men|Women)\b\s*(.*)$/i', $src, $m)) {
                $female = strcasecmp($m[2], 'Women') === 0;
                $gender = $female
                    ? __('personal.event_show_women')
                    : __('personal.event_show_men');

                if ($translated) {
                    $label = $gender;
                    $chip = $name;                                  // the whole translated name
                } else {
                    $label = trim(trim($m[1]).' '.$gender);
                    $chip = trim($m[3]) !== '' ? trim($m[3]) : $name;
                }

                $fallback = false;
            } else {
                $female = false;
                $fallback = true;
                $chip = $name;
                $label = __('personal.event_show_divisions');
            }

            $key = ($female ? 'f' : 'm').'|'.$label;

            $groups[$key] ??= ['label' => $label, 'female' => $female, 'fallback' => $fallback, 'items' => []];
            $groups[$key]['items'][] = $chip;
        }

        // No sort: PHP keeps insertion order, which is the divisions' own
        // sort_order — the sequence the organiser arranged them in. Imposing an
        // alphabetical order here would quietly override it.
        return array_values($groups);
    }
}
