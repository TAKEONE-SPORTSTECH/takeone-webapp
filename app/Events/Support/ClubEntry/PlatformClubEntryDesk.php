<?php

namespace App\Events\Support\ClubEntry;

use App\Events\Models\ClubEvent;
use App\Events\Support\CategorySystems;
use App\Events\Support\EventAccess;
use App\Events\Support\EventAddress;
use App\Events\Support\EventDoors;
use App\Events\Support\PublicEvent;
use App\Support\Countries;
use Illuminate\Http\Request;

/**
 * The CLUB-hosted event's back office behind the shared "Register your club"
 * screens — TakeOne clubs and the event's own entry records
 * (PlatformClubEntry). Chosen by App\Events\Events::clubEntryDesk() for an
 * event with no external host that takes club entries, while
 * `events.club_entry_platform` is on.
 */
final class PlatformClubEntryDesk implements ClubEntryDesk
{
    private PlatformClubEntry $entry;

    public function __construct(private ClubEvent $event)
    {
        $this->entry = new PlatformClubEntry($event);
    }

    public function service(): PlatformClubEntry
    {
        return $this->entry;
    }

    public function prepare(Request $request): void
    {
        $this->entry->bindSignedIn($request->user());
    }

    public function subject(Request $request): ?array
    {
        $club = $this->entry->sessionClub();

        return $club ? ['key' => $this->entry->keyOf($club), 'name' => $club instanceof \App\Clubs\Models\Tenant ? $club->club_name : $club->name] : null;
    }

    /** The event's own link — it opens for whoever holds it when the event is public or hidden. */
    public function invitationUrl(): ?string
    {
        return app(PublicEvent::class)->isPublic($this->event) ? route('me.events.show', ['event' => $this->event->uuid]) : null;
    }

    public function page(Request $request): array
    {
        $ev = $this->event;
        $e = (array) app(PublicEvent::class)->readerPayload($ev);
        if (! app(PublicEvent::class)->isPublic($ev)) {
            $e['manifest'] = null;
        }
        $me = $request->user();
        $v = fn () => substr(md5((string) @filemtime(public_path('libraries/takeone-club-entry/club-entry-store.js'))), 0, 8);
        $system = rescue(fn () => app(CategorySystems::class)->forEvent($ev), null, report: false);
        $u = fn (string $verb) => route('events.club-entry.api.'.$verb, ['event' => $ev->uuid]);

        return [
            'e' => $e,
            'doors' => EventDoors::public($e, new EventAddress($ev)),
            'event' => $ev,
            'coverLanguages' => null,          // the event's own (cover.blade.php)
            'languageUrls' => [],              // the event's own (language-sheet.blade.php)
            'console' => $me && app(EventAccess::class)->canManage($ev, $me) ? route('me.events.manage', ['event' => $ev->uuid]) : null,
            'strings' => (array) trans('events::club_entry.ui'),
            'noindex' => true,
            'scripts' => [['src' => asset('libraries/takeone-club-entry/club-entry-store.js').'?v='.$v(), 'defer' => false]],
            'boot' => [
                'csrf' => csrf_token(),
                'mode' => 'public',
                'event' => $ev->uuid,
                'testMode' => false,
                'store' => $this->entry->store(),
                'dial' => collect(Countries::dialCodes())
                    ->map(fn ($c) => ['i2' => strtoupper($c['flag']), 'i3' => Countries::iso3($c['flag']), 'name' => $c['name'], 'code' => $c['code']])
                    ->filter(fn ($c) => $c['i3'] !== 'UNK')->values()->all(),
                // What a club's event does not have: officials, transfers between clubs, national IDs.
                // …and what it has that a federation's does not: one payment proof per club.
                'features' => ['officials' => false, 'transfers' => false, 'nationalId' => false, 'proof' => true],
                'rulebook' => $system ? ['key' => $system->key, 'label' => $system->label, 'age_groups' => $system->ageGroups, 'classes' => $system->table()] : null,
                'urls' => [
                    'clubs' => $u('clubs'), 'code' => $u('code'), 'verify' => $u('verify'), 'choose' => $u('choose'),
                    'checkClub' => $u('check-club'), 'submit' => $u('submit'), 'withdraw' => $u('withdraw'), 'proof' => $u('proof'),
                ],
            ],
        ];
    }
}
