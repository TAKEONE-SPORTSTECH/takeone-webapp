<?php

namespace App\Events\Sports\Karate\Tournament;

use App\Models\ClubEvent;

/**
 * How a Karate championship reads its entrant list.
 *
 * Each row shows the division the competitor is REGISTERED in. Because
 * registration already guarantees that division is (a) one the organiser is
 * running and (b) a match for the member's own gender/age/weight, the tokens
 * are read back off the division name rather than re-classified live — so a
 * competitor can never appear under a class this championship isn't running,
 * and a man can never surface under a women's class.
 *
 * Entrants with no weight on file are held back: they cannot be drawn and would
 * only pad the roster.
 */
class Roster
{
    /** @return array<int, array<string, mixed>> */
    public function rows(ClubEvent $event): array
    {
        return $event->participantRegistrations()
            ->with([
                'user:id,full_name,name,gender,birthdate',
                'user.latestHealthRecord',
                // For BeltRank: resolving a rank per athlete would otherwise be a
                // query each, on a list that can run to hundreds of entrants.
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'category:id,name,weight_class',
            ])
            ->latest('registered_at')->get()
            ->map(function ($r) {
                $user = $r->user;
                $weightOnFile = $r->weight ?: $user?->latestHealthRecord?->weight;

                [$ageGroup, $weightClass] = $this->splitDivision(
                    $r->category?->name,
                    $r->category?->weight_class,
                );

                return [
                    'id' => $user?->id,
                    'name' => $user?->full_name ?? $user?->name ?? 'Member',
                    'gender' => $user?->gender ?: null,
                    'category' => $ageGroup,
                    'weight_class' => $weightClass,
                    'meta' => $user?->gender ?: ($ageGroup ? __('event-karate_tournament::messages.roster_registered') : __('event-karate_tournament::messages.roster_unclassified')),
                    // The three things an organiser checks off before a
                    // competitor can be drawn. `weighed` is the OFFICIAL
                    // weigh-in (weighed_in_at), which is not the same as
                    // `weighed_in` below — that only means a weight is on file.
                    'country' => $r->meta ?: null,
                    'enrolled' => $r->status === 'joined',
                    // Claimed vs verified: amber until an official has put their
                    // name to it (paid_by / weighed_in_by), then green.
                    'paid' => (bool) $r->paid,
                    'paid_verified' => $r->paid && $r->paid_by !== null,
                    'weighed' => $r->weighed_in_at !== null,
                    'weighed_verified' => $r->weighed_in_at !== null && $r->weighed_in_by !== null,
                    // What the arena screen will announce. Null when nothing is
                    // on file anywhere — the desk then asks the official for it.
                    'belt' => $user ? app(\App\Sports\Combat\BeltRank::class)->for($user, $r) : null,
                    'weighed_in' => $r->weight !== null,
                    'has_weight' => $weightOnFile !== null,
                ];
            })
            ->filter(fn ($row) => $row['has_weight'])
            ->values()->all();
    }

    /**
     * Split a canonical division name — "<Age group> <Men|Women> <label> kg" —
     * back into its age group and weight label.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitDivision(?string $name, ?string $weightClass): array
    {
        if (! $name) {
            return [null, null];
        }

        $parts = preg_split('/\s+(?:Men|Women)\s+/', $name, 2);

        return [$parts[0] ?? $name, $parts[1] ?? ($weightClass ?: null)];
    }
}
