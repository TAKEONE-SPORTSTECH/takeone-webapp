<?php

namespace App\Events\Contracts;

use App\Events\Models\ClubEvent;
use App\Members\Models\User;

/**
 * An event type that brings its own organiser WORKSPACE — the screen behind
 * the console's draw tile, where the competition is drawn and run.
 *
 * Combat packages do not implement it: their workspace is the shared draw
 * stage. A package that does (App\Events\Championship) is shown its own view,
 * for the people who may manage the event, in place of the stage. Readers keep
 * the shared draw page either way.
 */
interface ProvidesWorkspace
{
    /** The view name, e.g. `event-sport_championship::mobile.workspace`. */
    public function workspaceView(): string;

    /** Everything that view needs, for a viewer who may manage the event. */
    public function workspaceData(ClubEvent $event, User $viewer): array;
}
