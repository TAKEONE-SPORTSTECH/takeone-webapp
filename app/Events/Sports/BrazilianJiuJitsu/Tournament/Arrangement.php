<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\Support\Tournament\Arrangement as SharedArrangement;

/**
 * Organiser arrangement of a jiu-jitsu draw — shared behaviour, this
 * package's wording.
 */
class Arrangement extends SharedArrangement
{
    public function __construct(Advancement $advancement)
    {
        parent::__construct($advancement);
    }

    protected function langNamespace(): string
    {
        return 'event-bjj_tournament';
    }
}
