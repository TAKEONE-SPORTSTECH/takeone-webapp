<?php

namespace App\Sports\Combat;

use App\Models\ClubEventRegistration;
use App\Models\MemberCertification;
use App\Models\SkillAcquisition;
use App\Models\User;

/**
 * What belt an athlete is announced at.
 *
 * The arena screen introduces a competitor with their rank, and the answer can
 * come from three places that disagree. This resolves them in one order, once,
 * so every screen in the product announces the same thing.
 *
 * The order is by RECENCY OF VERIFICATION, not by richness of data:
 *
 *   1. The weigh-in for THIS event. An official stood in front of the athlete
 *      today, checked them, and signed a row. Nothing in a profile outranks
 *      that — a competitor who graded last month has the new belt on, and the
 *      desk recorded it.
 *   2. A certification on their profile. A dated award with an issuer, which is
 *      what a grading actually produces. The most recent one wins.
 *   3. A skill record's proficiency level. Weakest of the three: it is the
 *      athlete's own description of where they are at, and it carries no date
 *      of award — but it is better than announcing nothing.
 *
 * Returns null when all three are silent, and the caller HIDES the element
 * rather than printing a placeholder. Guessing a belt on a hall screen is worse
 * than leaving a gap: rank is the one thing in that stat line an audience will
 * read as an official fact.
 *
 * Lives in the combat module rather than in a sport package because every
 * combat sport ranks by belt and none of them ranks the same way — the parsing
 * here is deliberately vocabulary-free, so Karate's dan and Taekwondo's gup both
 * survive it untouched.
 */
class BeltRank
{
    /** Colours recognised when splitting a free-text certification title. */
    private const COLOURS = [
        'black', 'red', 'brown', 'purple', 'blue', 'green',
        'orange', 'yellow', 'white', 'grey', 'gray',
    ];

    /**
     * The belt to announce, or null when nothing is recorded.
     *
     * @return array{colour: ?string, grade: ?string, label: string, source: string}|null
     */
    public function for(User $user, ?ClubEventRegistration $registration = null): ?array
    {
        return $this->fromWeighIn($registration)
            ?? $this->fromCertification($user)
            ?? $this->fromSkill($user);
    }

    /** An official recorded it at the desk today. Authoritative. */
    private function fromWeighIn(?ClubEventRegistration $registration): ?array
    {
        if (! $registration || (! $registration->belt_colour && ! $registration->belt_grade)) {
            return null;
        }

        return $this->shape($registration->belt_colour, $registration->belt_grade, 'weigh_in');
    }

    /**
     * The most recent grading on their profile.
     *
     * Titles are free text a member typed ("Black Belt 2nd Dan", "1st Dan",
     * "Blue Belt"), so the colour is picked out by name and whatever else the
     * title says becomes the grade. A title that names no colour still yields a
     * grade, which is how "2nd Dan" on its own survives.
     */
    private function fromCertification(User $user): ?array
    {
        // Uses the relation when the caller eager-loaded it. The officials' desk
        // resolves a belt for every entrant in the event at once, and querying
        // per athlete there would be a couple of hundred round trips for a
        // screen that draws one list.
        $titles = $user->relationLoaded('certifications')
            ? $user->certifications
                ->sortByDesc(fn ($c) => [$c->issue_date, $c->id])
                ->pluck('title')
            : MemberCertification::where('user_id', $user->id)
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->pluck('title');

        foreach ($titles as $title) {
            if ($parsed = $this->parse((string) $title)) {
                return $parsed + ['source' => 'certification'];
            }
        }

        return null;
    }

    /** Their own stated proficiency in the sport. Weakest source, still real. */
    private function fromSkill(User $user): ?array
    {
        // Eager-loaded when the caller has a list to resolve — see the note on
        // fromCertification() above.
        $levels = $user->relationLoaded('skillAcquisitions')
            ? $user->skillAcquisitions
                ->whereNotNull('proficiency_level')
                ->sortByDesc(fn ($s) => [$s->start_date, $s->id])
                ->pluck('proficiency_level')
            : SkillAcquisition::where('user_id', $user->id)
                ->whereNotNull('proficiency_level')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->pluck('proficiency_level');

        foreach ($levels as $level) {
            if ($parsed = $this->parse((string) $level)) {
                return $parsed + ['source' => 'skill'];
            }
        }

        return null;
    }

    /**
     * Split free text into a colour and a grade.
     *
     * Only a recognised colour word is treated as a colour; everything else that
     * survives becomes the grade verbatim. Nothing is translated, abbreviated or
     * normalised — a federation's own wording reaches the screen as written.
     */
    private function parse(string $text): ?array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return null;
        }

        $colour = null;
        foreach (self::COLOURS as $candidate) {
            if (preg_match('/\b'.$candidate.'\b/i', $text, $m)) {
                $colour = ucfirst(strtolower($m[0]));
                // Remove the colour and the word "belt" that usually trails it.
                $text = trim(preg_replace('/\b'.$candidate.'\b\s*(belt)?/i', '', $text, 1));
                break;
            }
        }

        $grade = trim($text, " \t·-–—,");

        return ($colour || $grade !== '')
            ? $this->shape($colour, $grade ?: null, 'parsed')
            : null;
    }

    /** One shape for every source, with the label the screen prints. */
    private function shape(?string $colour, ?string $grade, string $source): array
    {
        $colourLabel = $colour ? trim($colour).' Belt' : null;

        return [
            'colour' => $colour,
            'grade' => $grade,
            // "Black Belt · 2nd Dan", or whichever half exists.
            'label' => implode(' · ', array_filter([$colourLabel, $grade])),
            'source' => $source,
        ];
    }
}
