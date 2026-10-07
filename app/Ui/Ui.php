<?php

namespace App\Ui;

use App\Support\Modules\AbstractModule;

/**
 * The new UI: one shell for the whole member experience (desktop top bar,
 * phone header and bottom tabs, the Play/Manage switch, the AI button) and the
 * screens rebuilt on it.
 *
 * It owns no data. It renders what the other modules already hold, through
 * their public models and existing endpoints, so deleting this folder (and
 * its line in config/modules.php) returns the platform to the old design.
 *
 * Nothing here is visible unless App\Ui\NewUi::active() says so; see
 * config/features.php `new_ui` and Documentation/major-change/NEW-UI.md.
 */
class Ui extends AbstractModule
{
    public function key(): string
    {
        return 'ui';
    }
}
