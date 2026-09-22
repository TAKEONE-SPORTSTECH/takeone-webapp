<?php

namespace App\Events\Support\Tournament;

use App\Models\EventCategory;
use App\Models\EventMatch;

/**
 * "Carry this bout's outcome forward through the rounds."
 *
 * ── Why an interface, for one method ──────────────────────────────────────
 *
 * `Advancement` exists three times: the shared base in this namespace, which
 * Brazilian Jiu-Jitsu extends, and standalone copies inside the Karate and
 * Taekwondo packages — the triplication CLAUDE.md records under *Shared Stays
 * Shared* and which is waiting on a deliberate consolidation.
 *
 * A shared collaborator that needs advancement therefore cannot type-hint the
 * base class without silently becoming BJJ-only. This names the one capability
 * it actually needs, so `LateEntry` works for every sport today and keeps
 * working after the three are merged into one.
 *
 * It describes what the three already do. Nothing about them changes.
 */
interface PropagatesResults
{
    /**
     * Carry $from's outcome into the next round and clear whatever the change
     * invalidated.
     *
     * @return array<int, array> the bouts that changed, for an in-place patch
     */
    public function propagate(EventCategory $category, EventMatch $from): array;
}
