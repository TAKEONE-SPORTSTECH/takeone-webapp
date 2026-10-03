<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;

/**
 * How a bracketed championship reads its entrant list.
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
abstract class Roster
{
    use SpeaksPackageLanguage;

    /** @return array<int, array<string, mixed>> */
    public function rows(ClubEvent $event): array
    {
        return $event->participantRegistrations()
            ->with([
                'user:id,full_name,name,gender,birthdate,nationality',
                'user.latestHealthRecord',
                // For BeltRank: resolving a rank per athlete would otherwise be a
                // query each, on a list that can run to hundreds of entrants.
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'category:id,name,weight_class',
            ])
            ->latest('registered_at')->get()
            ->map(function ($r) use ($event) {
                $user = $r->user;
                $weightOnFile = $r->weight ?: $user?->latestHealthRecord?->weight;

                [$ageGroup, $weightClass] = $this->splitDivision(
                    $r->category?->name,
                    $r->category?->weight_class,
                );

                return [
                    'id' => $user?->id,
                    'name' => $user?->full_name ?? $user?->name ?? 'Member',
                    // The ENTRY's own id and photo. Kept separate from the
                    // user's picture: this belongs to the entry, and it is what
                    // an organiser's controls act on — putting a face on a
                    // competitor with no account, and striking a name off the
                    // list. AbstractEventType::rosterRows() has always carried
                    // both; this shared tournament roster did not, so every
                    // organiser control on the roster page silently rendered
                    // nothing for every combat sport.
                    'registration' => $r->id,
                    'registration_photo' => $r->photo,
                    'gender' => $user?->gender ?: null,
                    /*
                     * Age ON THE DAY THE EVENT RUNS, which is the same rule the
                     * divisions judge by (DivisionRange::ageOf) — so the number
                     * on a card and the weight class it sits in can never
                     * disagree. An event built in March for December counts a
                     * borderline junior by the age they will BE.
                     *
                     * This shape carried no age at all, so every entrant card
                     * on the roster — organiser page and public list alike —
                     * printed an em dash for it (reported 2026-09-12). Null
                     * stays null: no birthdate on file is the ordinary case and
                     * not a fault, because staff entering somebody else are
                     * asked for a name and nothing more.
                     */
                    'age' => $user?->birthdate
                        ? (int) \Illuminate\Support\Carbon::parse($user->birthdate)->diffInYears(
                            $event->date ? \Illuminate\Support\Carbon::parse($event->date) : \Illuminate\Support\Carbon::now()
                        )
                        : null,
                    'category' => $ageGroup,
                    /*
                     * The division's own id, beside the words for it.
                     *
                     * `category` and `weight_class` above are DERIVED strings —
                     * one canonical name split back into its two halves — and a
                     * string cannot be linked to. A card that draws a division
                     * chip needs somewhere for that chip to go (the group's own
                     * draw), and every list that folds entries into one row per
                     * person needs a stable key to fold on. Both were doing it
                     * by name until 2026-09-17.
                     *
                     * Additive: nothing that reads this row today looks for it,
                     * and it is null for an entry no division has claimed yet —
                     * which is the ordinary state of an entry on the morning.
                     */
                    'category_id' => $r->category_id,
                    'weight_class' => $weightClass,
                    // The division's WHOLE name ("Séniors Men -75 kg"). `category`
                    // above is only its age half, and a chip that names the
                    // division must say all of it (RosterPeople::byPerson).
                    'division_name' => $r->category?->name,
                    'meta' => $user?->gender ?: ($ageGroup ? $this->t('roster_registered') : $this->t('roster_unclassified')),
                    // The three things an organiser checks off before a
                    // competitor can be drawn. `weighed` is the OFFICIAL
                    // weigh-in (weighed_in_at), which is not the same as
                    // `weighed_in` below — that only means a weight is on file.
                    'country' => $r->countryCode(),
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
