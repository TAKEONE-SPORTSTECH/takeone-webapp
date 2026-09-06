<?php

namespace App\EventLab\Services;

use App\EventLab\Models\LabClub;
use App\EventLab\Models\LabEntrant;
use App\EventLab\Models\LabEntry;
use App\EventLab\Models\LabEntrySelection;
use App\EventLab\Models\LabEvent;
use App\EventLab\Models\LabVariant;
use App\Models\ClubEvent;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fills the sandbox with a copy of a real event, so the new way of taking
 * entries can be tried against data that has the shape of the real thing.
 *
 * Strictly one-way. It READS `club_events`, `club_event_registrations` and
 * `users`, and writes only `lab_*`. There is no path back: nothing here updates,
 * deletes or even touches a real row, which is why it is safe to run against a
 * live event that is about to happen.
 *
 * Names and photos are copied because a start list of "Athlete 1..29" tests
 * nothing about the screen that has to display real ones.
 */
class LabSeeder
{
    /**
     * Copy a real event into the sandbox.
     *
     * Idempotent by source: running it twice for the same event replaces the
     * sandbox copy rather than accumulating duplicates of it.
     */
    public function fromRealEvent(ClubEvent $source, User $actor, array $variantNames = ['Gi', 'No-Gi']): LabEvent
    {
        return DB::transaction(function () use ($source, $actor, $variantNames) {
            // A previous copy of the SAME source event is replaced. Only lab
            // rows are ever removed here.
            LabEvent::where('source_event_id', $source->id)->get()->each->delete();

            $event = LabEvent::create([
                'source_event_id' => $source->id,
                'tenant_id' => $source->tenant_id,
                'title' => $source->title,
                'sport' => $source->sport,
                'date' => $source->date,
                'end_date' => $source->end_date,
                'weigh_in_at' => $source->weigh_in_at,
                'entries_open_at' => $source->enrollment_starts_at,
                'entries_close_at' => $source->enrollment_ends_at,
                // Late from the day before the weigh-in, which is the rule we
                // are here to try out.
                'late_entry_from' => $source->weigh_in_at?->copy()->subDay(),
                'late_fee_amount' => 5,
                'fee_currency' => $source->fee_currency ?: 'BHD',
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            $base = (float) ($source->participant_fee_amount ?: 10);

            foreach (array_values($variantNames) as $i => $name) {
                LabVariant::create([
                    'lab_event_id' => $event->id,
                    'name' => $name,
                    'slug' => str($name)->slug()->toString(),
                    'fee_amount' => $base + ($i * 5),
                    'sort_order' => $i,
                ]);
            }

            $first = $event->variants()->first();

            $registrations = $source->registrations()
                ->where('role', 'participant')
                ->with(['user:id,full_name,name,birthdate,gender,nationality,profile_picture', 'representingTenant:id,club_name,country,logo'])
                ->get();

            foreach ($registrations as $reg) {
                $club = null;

                if ($reg->representingTenant) {
                    $club = LabClub::firstOrCreate(
                        ['lab_event_id' => $event->id, 'name' => $reg->representingTenant->club_name],
                        [
                            'country' => $reg->representingTenant->country,
                            'logo' => $reg->representingTenant->logo,
                            // Matched to a club that already exists.
                            'tenant_id' => $reg->representingTenant->id,
                        ]
                    );
                }

                $entrant = LabEntrant::create([
                    'lab_event_id' => $event->id,
                    'full_name' => $reg->user?->full_name ?: ($reg->user?->name ?: 'Unnamed entrant'),
                    'birthdate' => $reg->user?->birthdate,
                    'gender' => $reg->user?->gender,
                    'nationality' => $reg->user?->nationality,
                    'photo' => $reg->photo,
                    'belt_colour' => $reg->belt_colour,
                    'belt_grade' => $reg->belt_grade,
                    'lab_club_id' => $club?->id,
                    'status' => 'entered',
                    // Nobody is a member here. That is the experiment.
                    'user_id' => null,
                    'created_by' => $actor->id,
                ]);

                $entry = LabEntry::create([
                    'lab_event_id' => $event->id,
                    'lab_entrant_id' => $entrant->id,
                    'state' => 'accepted',
                    'entered_at' => $reg->registered_at ?: now(),
                    'is_late' => false,
                    'late_fee_amount' => 0,
                    'fee_total' => $first?->fee() ?: 0,
                    'fee_currency' => $event->fee_currency,
                    'fee_breakdown' => [
                        'lines' => $first ? [['variant_id' => $first->id, 'name' => $first->name, 'amount' => $first->fee()]] : [],
                        'late_amount' => 0,
                    ],
                    'payment_state' => $reg->paid ? 'paid' : 'unpaid',
                    'created_by' => $actor->id,
                ]);

                if ($first) {
                    LabEntrySelection::create([
                        'lab_entry_id' => $entry->id,
                        'lab_variant_id' => $first->id,
                        'weight' => $reg->weight,
                        'fee_amount' => $first->fee(),
                    ]);
                }
            }

            return $event->fresh();
        });
    }
}
