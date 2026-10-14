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
    public function payload(array $access, ?\App\Members\Models\User $viewer = null): object
    {
        $q = FederationEvent::whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'))
            ->whereNotNull('club_event_id');
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        $out = [];
        foreach ($q->get() as $fe) {
            $o = rescue(fn () => $this->of($fe, $viewer), null, report: true);
            if ($o) {
                $out[$fe->uuid] = $o;
            }
        }

        return (object) $out;
    }

    public function of(FederationEvent $fe, ?\App\Members\Models\User $viewer = null): ?array
    {
        $ev = $fe->club_event_id ? \App\Events\Models\ClubEvent::find($fe->club_event_id) : null;
        if (! $ev) {
            return null;
        }

        /*
         * May this viewer appoint, change and remove officials here? Asked of
         * EventAccess — the very rule the officiating endpoints enforce — so
         * the tab never offers a control the server would refuse. Only then
         * does the payload carry what an edit needs (the appointment's id,
         * its role key, club key and pay); a viewer who may only look gets
         * names, jobs and clubs, as before.
         */
        $manage = $viewer !== null && app(\App\Events\Support\EventAccess::class)->canManage($ev, $viewer);

        $people = EventOfficial::where('event_id', $ev->id)
            ->with('user:id,full_name,name,gender,profile_picture,profile_picture_is_public,updated_at')
            ->orderBy('id')->get()
            ->filter(fn (EventOfficial $o) => $o->user !== null)
            ->map(function (EventOfficial $o) use ($manage) {
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
                ] + ($manage ? [
                    'id' => $o->id,
                    'roleKey' => (string) $o->role,
                    'clubKey' => $club['slug'] ?? '',
                    'compensation' => (string) $o->compensation,
                    'fee' => $o->fee !== null ? (float) $o->fee : null,
                ] : []);
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
            // The officiating endpoints the tab writes through. Each re-checks
            // EventAccess::canManage and validates exactly as the sheet's own
            // form does; `{id}` is the appointment, scoped to this event there.
            'manage' => $manage ? [
                'list' => route('me.events.officials', $ev->uuid),
                'store' => route('me.events.officials.store', $ev->uuid),
                'person' => route('me.events.officials.person', $ev->uuid),
                'one' => route('me.events.officials.update', [$ev->uuid, '__ID__']),
            ] : null,
        ];
    }
}
