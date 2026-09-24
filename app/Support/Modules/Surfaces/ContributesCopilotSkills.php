<?php

namespace App\Support\Modules\Surfaces;

use App\Support\Copilot\CopilotSkill;

/**
 * A module that teaches the assistant to create one of its records.
 * A module with nothing for it to create does not implement this.
 */
interface ContributesCopilotSkills
{
    /** @return array<int, CopilotSkill> */
    public function copilotSkills(): array;
}
