<?php

namespace App\Events\Contracts;

use App\Models\EventCategory;

/**
 * An event type that can be asked to (re-)award one of its divisions.
 *
 * The medal convention is PRIVATE to a package — its Advancement is its own —
 * and shared code has no business constructing one. But shared code sometimes
 * changes something a podium depends on (the play-off for bronze being switched
 * on or off is the case this was written for) and must be able to say "award
 * this division again" without knowing how.
 *
 * So this is the named public entry point CLAUDE.md asks for instead of
 * reaching into a package: the type answers, or it does not implement this and
 * the caller leaves its podiums alone.
 */
interface AwardsDivisions
{
    /**
     * Re-derive and store this division's podium, if it is decided.
     *
     * @return array<int, array{place: int, name: ?string}>  the medals, or [] when it is not
     */
    public function awardDivision(EventCategory $category): array;
}
