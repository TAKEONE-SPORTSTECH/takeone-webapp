<?php

namespace App\Parties;

use App\Support\Modules\AbstractModule;

/**
 * Parties & relationships — who holds what role in which party, and since when
 * (Documentation/major-change/PLAN.md).
 *
 * Everyone and everything on TakeOne is a party: a person, a club, a chain, a
 * federation, an event — later a clinic, a trainer's practice, a venue. Every
 * link between two of them is ONE dated row in party_relations: ownership with
 * shares, a president's term, a coach's stint, a club's membership of a
 * federation. Nothing is overwritten; a relationship is ended.
 *
 * Its public doors sit at the module root because every other module calls
 * them: App\Parties\Relations (the only writer), App\Parties\Authority (the
 * one permission answer), App\Parties\Kinds and App\Parties\Roles.
 *
 * Switched by config('parties.enabled') / ('parties.shadow') — both OFF.
 */
class Parties extends AbstractModule
{
    public function key(): string
    {
        return 'parties';
    }
}
