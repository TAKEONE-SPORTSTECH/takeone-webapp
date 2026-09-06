<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\Support\Tournament\Enrolment as SharedEnrolment;

/**
 * Who may compete in a jiu-jitsu championship — shared gate, this package's
 * wording. The weight tables and classification come from the
 * BrazilianJiuJitsu combat-sport plug-in handed to the constructor.
 */
class Enrolment extends SharedEnrolment
{
    protected function langNamespace(): string
    {
        return 'event-bjj_tournament';
    }
}
