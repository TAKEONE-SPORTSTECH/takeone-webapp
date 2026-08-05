<?php

namespace App\Events\Sports\Taekwondo\Tournament;

use App\Models\ClubEvent;

/**
 * How a Taekwondo championship reads its entrant list.
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
                    'meta' => $user?->gender ?: ($ageGroup ? __('event-taekwondo_tournament::messages.roster_registered') : __('event-taekwondo_tournament::messages.roster_unclassified')),
                    'paid' => (bool) $r->paid,
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
