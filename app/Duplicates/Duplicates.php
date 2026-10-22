<?php

namespace App\Duplicates;

use App\Support\Modules\AbstractModule;

/**
 * One person, one record; one club, one record.
 *
 * The platform has twenty-five doors that create a person and seventeen that
 * create a club, and on a multi-tenant platform a door cannot simply SHOW the
 * club typing into it who else matches — that would hand one club's members to
 * another. So duplicates are fought in two places:
 *
 *   · at the DOOR, blind: a key the person typing already knows (a phone with
 *     the name, a national ID, a TakeOne ID, an exact club name) links to the
 *     record that exists, and the screen says only "linked". Those guards live
 *     in the modules that own each door, behind `duplicates.guard.*`.
 *   · AFTERWARDS, here: the duplicate watch (`duplicates:watch`) scans the
 *     whole platform for pairs that slipped through, records each as a
 *     finding, fixes the CERTAIN ones itself when `duplicates.autofix` is on
 *     (every fix reversible — a merge through AccountMerger, which keeps an
 *     undo ledger; a club link, which only fills empty fields), and leaves the
 *     rest for a super-admin to decide.
 *
 * Delete test: remove app/Duplicates/, its table and its line in
 * config/modules.php — every guard reads `duplicates.guard.*`, which is then
 * unset, so each door falls back to exactly what it did before.
 */
class Duplicates extends AbstractModule
{
    public function key(): string
    {
        return 'duplicates';
    }
}
