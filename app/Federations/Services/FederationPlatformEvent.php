<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventFeeOption;
use App\Federations\Models\FederationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A federation's competition, RUNNING as a platform event (2026-09-28).
 *
 * The federation writes its event on its dashboard; this keeps one platform
 * event (club_events) in step with it, hosted by the FEDERATION
 * (host_type 'federation', tenant_id NULL — never a club). Everything a club's
 * competition has — the console, divisions, the draw, hall screens, the
 * scoreboard and its control, the next-up queue, mat cameras, results — then
 * works on it unchanged, because it IS the same kind of event. The only thing
 * that differs is how athletes arrive: through the club invitation
 * (FederationInvitation), entered by their clubs.
 *
 * One direction only: the federation event is the source for everything it
 * holds, and a field it does not hold (the draw, the running order, screens,
 * results) is never touched here. Behind `federations.platform_events`.
 */
class FederationPlatformEvent
{
    public function enabled(): bool
    {
        return (bool) config('federations.enabled') && (bool) config('federations.platform_events');
    }

    /** The platform event behind this federation event, if there is one. */
    public function of(FederationEvent $fe): ?ClubEvent
    {
        return $fe->club_event_id ? ClubEvent::find($fe->club_event_id) : null;
    }

    /** Create or update the platform event. Null when switched off or the event cannot run yet. */
    public function sync(FederationEvent $fe): ?ClubEvent
    {
        if (! $this->enabled() || ! $fe->federation) {
            return null;
        }
        $d = (array) $fe->details;
        $start = $this->moment($fe->starts_on);
        if (! $start) {
            return $this->of($fe);   // no date yet: nothing to run; an existing one is left as it was
        }
        $end = $this->moment($fe->ends_on);
        $clock = fn (?string $v) => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? $v.':00' : null;
        $has = fn (?string $v) => $v !== null && strlen($v) > 10;

        return DB::transaction(function () use ($fe, $d, $start, $end, $clock, $has) {
            $ev = $this->of($fe) ?? new ClubEvent;
            $isNew = ! $ev->exists;
            $fed = $fe->federation;
            $registry = app(FederationRegistry::class);
            $invite = $registry->eventForInvite($fe);
            $texts = $registry->inviteTexts($fe)['en'];
            $sport = strtolower((string) $fe->sport);
            $venue = trim(implode(', ', array_filter([($d['venue'] ?? '') !== 'TBC' ? ($d['venue'] ?? '') : '', $d['city'] ?? ''])));
            $paid = collect($invite['fees'])->filter(fn ($f) => (float) $f['price'] > 0);

            $ev->forceFill([
                // The host is the federation. Never a club.
                'tenant_id' => null,
                'host_type' => 'federation',
                'host_id' => $fed->id,
                'created_by' => $ev->created_by ?? $fe->created_by,

                'title' => $fe->name,
                'description' => FederationEvent::aboutText($d['about'] ?? ''),
                'sport' => $sport,
                'event_type' => $ev->event_type && $ev->event_type !== 'class' ? $ev->event_type : 'championship',
                'scope' => 'nationwide',
                'status' => $ev->status === 'cancelled' ? 'cancelled' : 'active',
                'is_archived' => false,
                // Nobody enters themselves: clubs enter their athletes through
                // the invitation (FederationInvitation → entries).
                'entry_mode' => 'members',
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $fed->color) ? $fed->color : '#1e2c4f',
                'icon' => 'bi-trophy',

                'date' => $start->toDateString(),
                'end_date' => $end && ! $end->isSameDay($start) ? $end->toDateString() : null,
                'start_time' => $clock($d['dayStart'] ?? null) ?? ($has($fe->starts_on) ? $start->format('H:i:s') : '09:00:00'),
                'end_time' => $clock($d['dayEnd'] ?? null) ?? ($end && $has($fe->ends_on) ? $end->format('H:i:s') : null),
                'break_start' => ! empty($d['breakOn']) ? $clock($d['breakFrom'] ?? null) : null,
                'break_end' => ! empty($d['breakOn']) ? $clock($d['breakTo'] ?? null) : null,
                'weigh_in_at' => ($w = $this->moment($d['weighIn'] ?? null)) ? $w : null,
                'enrollment_starts_at' => ($s = $this->moment($d['enrollStart'] ?? null)) ? $s->toDateString() : null,
                'enrollment_ends_at' => ($x = $this->moment($d['enrollEnd'] ?? null)) ? $x->toDateString() : null,
                'courts' => is_numeric($d['mats'] ?? null) ? max(1, min(50, (int) $d['mats'])) : ($ev->courts ?: null),

                'location' => $venue !== '' ? $venue : null,
                'location_url' => preg_match('#^https?://#i', (string) ($texts['mapUrl'] ?? '')) ? $texts['mapUrl'] : null,
                'gps_lat' => is_numeric($texts['mapLat'] ?? null) ? (float) $texts['mapLat'] : null,
                'gps_long' => is_numeric($texts['mapLng'] ?? null) ? (float) $texts['mapLng'] : null,

                'max_capacity' => $fe->capacity ?: null,
                'requirements' => array_values(array_filter(array_map(fn ($r) => is_string($r) ? trim($r) : '', (array) ($d['reqs'] ?? [])))),
                'fee_currency' => $invite['currency'] ?? 'BHD',
                'participant_fee_amount' => $paid->isEmpty() ? null : (float) $paid->min('price'),
                'participant_fee' => $paid->isEmpty() ? __('events::events.fee_free') : null,
                'late_fee_amount' => (float) ($invite['lateFee'] ?? 0) > 0 ? (float) $invite['lateFee'] : null,
                'late_fee_from' => (float) ($invite['lateFee'] ?? 0) > 0 && ($x ?? null) ? $x->copy()->addDay()->startOfDay() : null,

            ]);
            // Languages are the console's once the event exists (its Languages
            // tool edits them); the federation's choice only seeds a new one.
            if ($isNew) {
                $ev->forceFill(['offered_locales' => $fe->offeredLocales(), 'source_locale' => $fe->sourceLocale()]);
                // The rule book chosen on the dashboard (FederationEvents::setRuleBook);
                // afterwards changed through CategorySystems::change, never here.
                $ev->forceFill(['category_system' => $d['categorySystem'] ?? null, 'belt_system' => $d['beltSystem'] ?? null]);
            }
            if ($isNew && ! $ev->uuid) {
                $ev->uuid = (string) Str::uuid();
            }
            $ev->save();

            $this->fees($ev, $invite['fees']);
            $this->streams($ev);

            if ((int) $fe->club_event_id !== (int) $ev->id) {
                $fe->forceFill(['club_event_id' => $ev->id])->saveQuietly();
                // First linked: the entries the clubs already made come across.
                app(FederationPlatformEntries::class)->sync($fe->fresh());
            }

            return $ev;
        });
    }

    /**
     * The console edited the platform event: bring the federation's own record
     * into step (the organiser edits in the console — asked for 2026-09-28).
     *
     * Only a field that actually CHANGED is written back: each is compared
     * with what the forward sync would have produced from the federation's
     * record, so the dashboard's richer text (an About with bold and lists) is
     * never flattened unless somebody edited that very field here.
     */
    public function pullBack(ClubEvent $ev): void
    {
        if (! $this->enabled() || $ev->host_type !== 'federation') {
            return;
        }
        $fe = FederationEvent::where('club_event_id', $ev->id)->first();
        if (! $fe) {
            return;
        }
        $raw = fn (string $k) => $ev->getRawOriginal($k);
        $hm = fn ($t) => $t ? substr((string) $t, 0, 5) : null;
        $d = (array) $fe->details;
        $date = $ev->date?->toDateString();
        $endDay = $ev->end_date?->toDateString() ?: $date;

        $fe->name = (string) $raw('title');
        if ($date) {
            $fe->starts_on = $date.($hm($ev->start_time) ? 'T'.$hm($ev->start_time) : '');
            $fe->ends_on = $endDay ? $endDay.($hm($ev->end_time) ? 'T'.$hm($ev->end_time) : '') : null;
        }
        $fe->capacity = $ev->max_capacity ?: null;

        $venueNow = trim(implode(', ', array_filter([($d['venue'] ?? '') !== 'TBC' ? ($d['venue'] ?? '') : '', $d['city'] ?? ''])));
        if ((string) $raw('location') !== $venueNow) {
            $d['venue'] = (string) ($raw('location') ?: 'TBC');
            $d['city'] = '';
        }
        $desc = (string) $raw('description');
        if ($desc !== FederationEvent::aboutText($d['about'] ?? '')) {
            $d['about'] = FederationHtml::clean(collect(preg_split("/\n{2,}/", trim($desc)) ?: [])
                ->filter(fn ($p) => trim($p) !== '')
                ->map(fn ($p) => '<p>'.nl2br(e(trim($p)), false).'</p>')->implode(''));
        }
        $d['reqs'] = array_values(array_filter(array_map('strval', (array) ($ev->requirements ?: []))));
        // The rule book, as the console last set it.
        $d['categorySystem'] = $ev->category_system ?: null;
        $d['beltSystem'] = $ev->belt_system ?: null;
        $d['weighIn'] = $ev->weigh_in_at ? $ev->weigh_in_at->format('Y-m-d\TH:i') : '';
        $d['enrollStart'] = $ev->enrollment_starts_at?->toDateString() ?? '';
        $d['enrollEnd'] = $ev->enrollment_ends_at?->toDateString() ?? '';
        $d['dayStart'] = $hm($ev->start_time) ?? '';
        $d['dayEnd'] = $hm($ev->end_time) ?? '';
        $d['breakOn'] = (bool) ($ev->break_start && $ev->break_end);
        $d['breakFrom'] = $hm($ev->break_start) ?? '';
        $d['breakTo'] = $hm($ev->break_end) ?? '';
        $d['mats'] = $ev->courts ?: ($d['mats'] ?? null);
        if ($ev->gps_lat && $ev->gps_long) {
            $d['lat'] = (string) round((float) $ev->gps_lat, 5);
            $d['lng'] = (string) round((float) $ev->gps_long, 5);
        }

        // The fee list: one line per competition type, priced as the console
        // priced it. Team fees are the federation's own and are kept.
        $opts = EventFeeOption::where('event_id', $ev->id)->where('role', 'participant')->where('is_active', true)->orderBy('sort')->get();
        $teamTypes = collect($d['types'] ?? [])->filter(fn ($t) => str_starts_with(mb_strtolower((string) $t), 'team '));
        $teamFees = collect($d['fees'] ?? [])->filter(fn ($f) => is_array($f) && $teamTypes->contains($f['type'] ?? null));
        if ($opts->isNotEmpty()) {
            $d['types'] = $opts->pluck('label')->merge($teamTypes)->unique()->values()->all();
            $d['fees'] = $opts->map(fn ($o) => ['label' => (string) $o->label, 'amount' => rtrim(rtrim(number_format((float) $o->amount, 3, '.', ''), '0'), '.'), 'type' => (string) $o->label])
                ->merge($teamFees)->values()->all();
            $d['free'] = $opts->every(fn ($o) => (float) $o->amount <= 0) && $teamFees->isEmpty();
        }
        $d['lateFee'] = $ev->late_fee_amount ? rtrim(rtrim(number_format((float) $ev->late_fee_amount, 3, '.', ''), '0'), '.') : '';

        $fe->details = $d;
        $fe->saveQuietly();
    }

    /** The federation event was deleted: its platform event is archived with it (never deleted — entries and bouts stay). */
    public function archive(FederationEvent $fe): void
    {
        if ($ev = $this->of($fe)) {
            $ev->forceFill(['is_archived' => true])->save();
        }
    }

    /** One fee option per competition type, priced as the federation priced it. A type dropped is switched off, never deleted. */
    private function fees(ClubEvent $ev, array $fees): void
    {
        $existing = EventFeeOption::where('event_id', $ev->id)->where('role', 'participant')->get()->keyBy('label');
        $seen = [];
        foreach (array_values($fees) as $i => $f) {
            if (($f['unit'] ?? 'athlete') !== 'athlete') {
                continue;   // team fees are the club's, invoiced by the federation
            }
            $label = (string) $f['type'];
            $seen[] = $label;
            $opt = $existing->get($label) ?? new EventFeeOption(['uuid' => (string) Str::uuid(), 'event_id' => $ev->id, 'role' => 'participant', 'label' => $label]);
            $opt->fill(['amount' => (float) $f['price'], 'is_active' => true, 'sort' => $i])->save();
        }
        $existing->each(function (EventFeeOption $o) use ($seen) {
            if (! in_array($o->label, $seen, true) && $o->is_active) {
                $o->forceFill(['is_active' => false])->save();
            }
        });
    }

    /**
     * Several individual competition types — Kumite and Kata — are separate
     * STREAMS of one event (App\Events\Support\EventStreams): entered and
     * drawn apart, each fee option granting its own. One type is no stream at
     * all, which is how an event that only fights has always run.
     */
    private function streams(ClubEvent $ev): void
    {
        $opts = EventFeeOption::where('event_id', $ev->id)->where('role', 'participant')->where('is_active', true)->orderBy('sort')->get();
        $keys = $opts->mapWithKeys(fn ($o) => [(string) $o->label => \App\Events\Support\EventStreams::key((string) $o->label)])->filter();
        $current = collect(\App\Events\Support\EventStreams::of($ev))->pluck('key');

        if ($keys->count() > 1) {
            $ev->forceFill(['streams' => $keys->map(fn ($k, $label) => ['key' => $k, 'label' => $label])->values()->all()])->save();
            foreach ($opts as $o) {
                $k = $keys[(string) $o->label] ?? null;
                if ($k && (array) $o->grants !== [$k]) {
                    $o->forceFill(['grants' => [$k]])->save();
                }
            }
        } elseif ($current->isNotEmpty() && $current->diff(EventFeeOption::where('event_id', $ev->id)->pluck('label')
            ->map(fn ($l) => \App\Events\Support\EventStreams::key((string) $l)))->isEmpty()) {
            // Down to one type again, and the streams were ours: none.
            $ev->forceFill(['streams' => null])->save();
        }
    }

    /** Is this competition type divided by weight? Forms (kata, poomsae) are not — by age and gender only. */
    public static function weighed(string $type): bool
    {
        return ! preg_match('/\b(kata|poomsae|forms?|hyung|kihon)\b/i', $type);
    }

    /** Is this a TEAM type? Teams are the club's entry on the federation's side, not a person's here. */
    public static function isTeam(string $type): bool
    {
        return str_starts_with(mb_strtolower(trim($type)), 'team ');
    }

    private function moment(?string $v): ?Carbon
    {
        return $v ? rescue(fn () => Carbon::parse($v), null, report: false) : null;
    }
}
