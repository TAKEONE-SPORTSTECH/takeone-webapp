<?php

namespace App\DevBoard;

use App\Support\Modules\AbstractModule;

/**
 * Margin — the development board.
 *
 * A private, shared board for the platform's owner and their assistant: capture
 * an idea the moment it is seen (text, a photo marked up with a pen, a sketch),
 * then move it through a pipeline of lanes until it is done.
 *
 * Not a club feature and not a member feature: only the people listed in
 * `devboard_members` (and the super-admin) can open it — see Support\BoardAccess.
 * Off unless DEVBOARD_ENABLED.
 *
 * Delete test: remove app/DevBoard/, its `devboard_*` tables, its line in
 * config/modules.php and public/vendor/{sortablejs,perfect-freehand}, and
 * nothing else notices. Plan: Documentation/DEV-BOARD-PLAN.md.
 */
class DevBoard extends AbstractModule
{
    public function key(): string
    {
        return 'devboard';
    }
}
