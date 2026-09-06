<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventOfficial;
use Illuminate\Support\Str;

/**
 * What each officiating job is CALLED, for one event.
 *
 * Two vocabularies meet on an officiating sheet and only one of them is ours:
 * the mat roles come from the SPORT (only Karate knows what a Kansa is, only
 * Taekwondo has a Sonsim), and the platform roles (jury, weigh-in, payments,
 * organiser) are the access this application grants. An event's sheet prints
 * both, in that order, because that is the order a competition reads them in.
 *
 * This lives here rather than in a controller because two surfaces now need
 * the same answer — the members' officiating sheet and the public event page —
 * and a second copy is how the two drift apart (CLAUDE.md → *Shared Stays
 * Shared*: add the seam, never duplicate the class).
 */
class OfficialRoles
{
    /**
     * Role key => human label, sport roles first.
     *
     * @return array<string, string>
     */
    public function labels(ClubEvent $event): array
    {
        $out = [];

        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);

        if ($sport !== null) {
            foreach ($sport->officialRoles() as $role) {
                if (! empty($role['key'])) {
                    $out[$role['key']] = $role['label'] ?? Str::title($role['key']);
                }
            }
        }

        foreach (EventOfficial::roles() as $role) {
            $out[$role] = __('personal.personal_event_officials_role_'.$role);
        }

        return $out;
    }
}
