<?php

namespace App\Events\Sports\Taekwondo\Tournament;

/**
 * The shared competitor-photo resolver, under the name Taekwondo's screens
 * already ask for.
 *
 * The whole of it moved to App\Events\Support\CompetitorPhoto when the Karate
 * and BJJ boards turned out to be answering the same question a different way —
 * they only ever looked at the member's account picture, so a face photographed
 * at the scoring desk never reached their upcoming board. Nothing about which
 * picture to show is a rule-book decision, so it is COMMON (CLAUDE.md → Shared
 * Stays Shared).
 *
 * This subclass carries no behaviour of its own and exists so the Taekwondo mat
 * and court display keep resolving and type-hinting exactly what they did.
 */
class CompetitorPhoto extends \App\Events\Support\CompetitorPhoto {}
