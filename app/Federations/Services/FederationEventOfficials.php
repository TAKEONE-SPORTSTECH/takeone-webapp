<?php

namespace App\Federations\Services;

use App\Events\Models\EventOfficial;
use App\Federations\Models\FederationEvent;

/**
 * Who is officiating each of a federation's events, for the event's Officials
 * tab on the dashboard — the event's OWN appointments (event_officials), so
 * the tab, the readiness checklist and the event's officiating sheet all
 * count the same people.
 *
 * It prints what the officiating sheet prints (PersonalEventController::
 * officiating): a name, the job, the club — and a face only on the sheet's
 * terms: the appointment's own photo, else a profile picture the member made
 * public. Email, phone and fee are appointment paperwork and never travel.
 */
class FederationEventOfficials
{
    public function __construct(private FederationDirectory $directory) {}

    /** `__TAKEONE_FED.eventOfficials`: event uuid => {url, total, groups: [{label, count, people}]}. */
    public function payload(array $access): object
    {
        $q = FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'))
            ->whereNotNull('club_event_id');
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        $out = [];
        foreach ($q->get() as $fe) {
            $o = rescue(fn () => $this->of($fe), null, report: true);
            if ($o) {
                $out[$fe->uuid] = $o;
            }
        }

        return (object) $out;
    }

    public function of(FederationEvent $fe): ?array
    {
        $ev = $fe->club_event_id ? \App\Events\Models\ClubEvent::find($fe->club_event_id) : null;
        if (! $ev) {
            return null;
        }

        $people = EventOfficial::where('event_id', $ev->id)
            ->with('user:id,full_name,name,gender,profile_picture,profile_picture_is_public,updated_at')
            ->orderBy('id')->get()
            ->filter(fn (EventOfficial $o) => $o->user !== null)
            ->map(function (EventOfficial $o) {
                $name = (string) ($o->user->full_name ?: $o->user->name);
                $club = $o->club();

                return [
                    'job' => FederationEventFigures::jobOf($o->role, $o->role_label),
                    'role' => (string) ($o->role_label ?: FederationEventFigures::jobOf($o->role, null)),
                    'name' => $name,
                    'initials' => mb_strtoupper(collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')),
                    'gender' => $o->user->gender === 'Female' ? 'F' : 'M',
                    'photo' => $o->photo
                        ? file_url($o->photo)
                        : (($o->user->profile_picture && $o->user->profile_picture_is_public)
                            ? file_url($o->user->profile_picture).'?v='.($o->user->updated_at?->timestamp ?? 0)
                            : null),
                    'club' => $club['name'] ?? null,
                    'clubLogo' => $club['logo'] ?? null,
                ];
            });

        $groups = $people->groupBy('job')
            ->map(fn ($list, $job) => [
                'label' => $job,
                'count' => $list->count(),
                // Inside a job: by club, then name — how a sheet is checked.
                'people' => $list->sortBy(fn ($p) => mb_strtolower(($p['club'] ?? '~').' '.$p['name']))->values()->all(),
            ])
            ->sortByDesc('count')->values()->all();

        return [
            'url' => route('me.events.officiating', $ev->uuid),
            'total' => $people->count(),
            'groups' => $groups,
        ];
    }
}
