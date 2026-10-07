<?php

namespace App\Federations\Support;

use App\Support\Cldr;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Federation events on a member's /me/events list (added 2026-09-28).
 *
 * A federation's competitions live in their own tables, so the events list
 * never saw them. This answers, for one member, the federation events they
 * should: those run by a federation (or any body above it) that one of the
 * member's clubs is an approved member of.
 *
 *   · listed from the moment it is created until it ENDS — past its end date
 *     (or start date, when it has no end) or marked Completed, it is gone,
 *     never kept as "past";
 *   · the card opens the federation's own public invitation page for the
 *     event — or, once the event runs as a platform event
 *     (FederationPlatformEvent), that event's own /me/events page;
 *   · each row is shaped like PersonalEventController::eventView()'s LIST
 *     card, plus `href`/`external`, so the same card markup draws it.
 *
 * Read-only, and fails closed: federations switched off, or anything going
 * wrong, is an empty list — the rest of the events page must never break.
 */
class MemberFederationEvents
{
    /** @return array<int, array<string, mixed>> */
    public static function for(User $user): array
    {
        if (! config('federations.enabled')) {
            return [];
        }

        return rescue(fn () => self::resolve($user), [], report: true);
    }

    private static function resolve(User $user): array
    {
        // The member's clubs: the ones they belong to, own, or administer.
        $tenants = DB::table('memberships')->where('user_id', $user->id)->where('status', 'active')->pluck('tenant_id')
            ->merge(DB::table('tenants')->where('owner_user_id', $user->id)->pluck('id'))
            ->merge(DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $user->id)->where('roles.slug', 'club-admin')
                ->whereNotNull('user_roles.tenant_id')->pluck('user_roles.tenant_id'))
            ->unique();

        // The federations those clubs belong to, plus any the member runs
        // (owner or staff) — and every usable body above them: a national
        // federation's event is for its regional clubs too.
        $ids = [];
        $frontier = FederationClub::whereIn('tenant_id', $tenants)->where('status', 'approved')->pluck('federation_id')
            ->merge(Federation::where('owner_user_id', $user->id)->pluck('id'))
            ->merge(DB::table('federation_staff')->where('user_id', $user->id)->pluck('federation_id'))
            ->unique()->values()->all();
        for ($depth = 0; $frontier && $depth <= Federation::MAX_DEPTH; $depth++) {
            $bodies = Federation::approved()->whereNull('suspended_at')->whereIn('id', $frontier)->get(['id', 'parent_id']);
            $ids = array_merge($ids, $bodies->pluck('id')->all());
            $frontier = array_values(array_diff($bodies->pluck('parent_id')->filter()->unique()->all(), $ids));
        }
        // A body the member RUNS also sees the events of the bodies beneath it.
        $run = Federation::approved()->whereNull('suspended_at')->where('owner_user_id', $user->id)->pluck('id')
            ->merge(DB::table('federation_staff')->where('user_id', $user->id)->pluck('federation_id'))->unique()->all();
        for ($depth = 0; $run && $depth <= Federation::MAX_DEPTH; $depth++) {
            $run = Federation::approved()->whereNull('suspended_at')->whereIn('parent_id', $run)
                ->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $run);
        }
        if ($ids === []) {
            return [];
        }

        $now = now();

        $events = FederationEvent::with('federation:id,parent_id,name,short_name,color,currency,timezone', 'federation.parent:id,currency')
            ->whereIn('federation_id', array_unique($ids))
            ->where('status', '!=', 'Completed')
            ->get()
            ->filter(fn (FederationEvent $e) => ! $e->hasEnded($now))
            ->values();

        // Paid featuring: an approved federation event may take the spotlight too.
        $featured = class_exists(\App\Events\Support\EventFeaturing::class)
            ? \App\Events\Support\EventFeaturing::featuredFederationEventIds($events->pluck('id'))->flip() : collect();

        // Running as a platform event (FederationPlatformEvent): the card opens
        // THAT page — the same event page, console and run day as a club's.
        $platform = app(\App\Federations\Services\FederationPlatformEvent::class)->enabled()
            ? \App\Events\Models\ClubEvent::whereIn('id', $events->pluck('club_event_id')->filter())
                ->where('is_archived', false)->where('host_type', 'federation')->get(['id', 'uuid'])->keyBy('id')
            : collect();
        $mine = $platform->isEmpty() ? collect() : DB::table('club_event_registrations')
            ->whereIn('event_id', $platform->keys())->where('user_id', $user->id)->where('role', 'participant')
            ->pluck('event_id')->flip();

        // A HIDDEN platform event is on nobody's list but those who run it or
        // are entered in it (ClubEvent::scopeListedFor) — reached by its link.
        $hiddenIds = \App\Events\Models\ClubEvent::whereIn('id', $platform->keys())->where('visibility', \App\Events\Models\ClubEvent::HIDDEN)->pluck('id');
        if ($hiddenIds->isNotEmpty()) {
            $mayList = \App\Events\Models\ClubEvent::whereIn('id', $hiddenIds)->listedFor($user)->pluck('id')->flip();
            // …and those who run the federation hosting it.
            $events = $events->reject(fn (FederationEvent $e) => $e->club_event_id && $hiddenIds->contains($e->club_event_id)
                && ! isset($mayList[$e->club_event_id]) && ! in_array((int) $e->federation_id, array_map('intval', (array) $run), true))->values();
        }

        return $events->map(function (FederationEvent $e) use ($featured, $platform, $mine) {
            $card = self::card($e) + ['featured' => isset($featured[$e->id])];
            if ($ev = $platform->get($e->club_event_id)) {
                $card['key'] = $ev->uuid;
                $card['href'] = route('me.events.show', ['event' => $ev->uuid]);
                $card['external'] = false;
                $card['joined'] = isset($mine[$ev->id]);
            }

            return $card;
        })->all();
    }

    private static function moment(?string $v, ?string $tz = null): ?Carbon
    {
        return $v ? rescue(fn () => Carbon::parse($v, $tz), null, report: false) : null;
    }

    private static function card(FederationEvent $e): array
    {
        $d = (array) $e->details;
        $start = self::moment($e->starts_on, $e->zone());
        $end = self::moment($e->ends_on, $e->zone());
        $hasTime = fn (?string $v) => $v !== null && strlen($v) > 10;
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $e->federation?->color) ? $e->federation->color : '#1e2c4f';
        $going = FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))
            ->where('status', 'Active')->count();
        $venue = collect([$d['venue'] ?? null, $d['city'] ?? null])->filter(fn ($v) => $v && $v !== 'TBC')->implode(', ');
        $fee = collect($d['fees'] ?? [])->first();
        $free = ($d['free'] ?? true) !== false || ! $fee;

        return [
            'id' => 'fed-'.$e->id,
            'key' => $e->uuid,
            'href' => route('federations.app.invitation').'?event='.rawurlencode($e->uuid),
            // The event's own front door — artwork and language picker — kept
            // even when `href` is switched to the platform event page below.
            'invitation' => route('federations.app.invitation').'?event='.rawurlencode($e->uuid),
            'external' => true,
            'source' => 'federation',
            'sort' => $start?->timestamp ?? PHP_INT_MAX,
            'day' => $start ? $start->format('d') : '—',
            'mon' => $start ? Cldr::skeleton($start, 'MMM', 'M') : '',
            'wday' => $start ? Cldr::skeleton($start, 'EEE', 'D') : '',
            'date_iso' => $start?->toDateString(),
            'title' => $e->name,
            'club' => $e->federation?->short_name ?: ($e->federation?->name ?? 'Federation'),
            'location' => $venue !== '' ? $venue : __('events::events.tba'),
            'time' => $start && $hasTime($e->starts_on) ? Cldr::time($start) : __('events::events.tba'),
            'end' => $end && $hasTime($e->ends_on) ? Cldr::time($end) : '',
            'level' => $e->sport ?: 'All',
            'tag' => $e->federation?->short_name ?: ($e->federation?->name ?? 'Federation'),
            'type' => 'Federation',
            'sport' => $e->sport,
            'icon' => 'bi-bank',
            'color' => $color,
            'going' => $going,
            'cap' => $e->capacity ?: max($going, 1),
            'capped' => (int) $e->capacity > 0,
            'participant_fee' => $free
                ? __('events::events.fee_free')
                : trim(($e->federation?->currency ?: ($e->federation?->parent?->currency ?? '')).' '.($fee['amount'] ?? '')),
            'spectator' => null,
            'participants' => [],
            'joined' => false,
            'started' => $start ? $start->lte(now()) : false,
            'ended' => false,
        ];
    }
}
