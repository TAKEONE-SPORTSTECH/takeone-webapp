<?php

namespace Ahl\Certificates\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Optional, for a Host whose templates belong to owners (a school, a club, one event).
 *
 * The chain is most specific first: [['event', 12], ['club', 3]] means "this event's own
 * template if it has one, otherwise the club's". The package reads the first that exists
 * and, when none does, creates the default for the LAST (broadest) owner.
 */
interface OwnsTemplates
{
    /** @return list<array{0:string,1:int}> */
    public function templateOwners(?Model $context): array;
}
