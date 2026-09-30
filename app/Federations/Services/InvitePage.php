<?php

namespace App\Federations\Services;

use App\Events\Support\CoverLanguages;
use App\Events\Support\EventFee;
use App\Events\Support\SportLabel;
use App\Federations\Models\FederationEvent;
use App\Federations\Support\FederationBrand;
use App\Support\Cldr;
use App\Translation\Translations;
use Illuminate\Support\Carbon;

/**
 * The club invitation, in the EVENT PAGE's vocabulary (2026-09-28).
 *
 * The invitation is drawn by the same partials as /me/events/{uuid} — the
 * cover and its language carousel, the band, the quick-facts card, the detail
 * card — and those read a payload shaped like App\Events\Support\PublicEvent's.
 * This builds that shape from a federation's event, in the reader's language:
 * the organiser's words through App\Translation (machine-translated on first
 * request, cached for ever), the dates through CLDR.
 *
 * It reads only what the invitation already publishes to any club holding the
 * link (FederationInvitation::store / FederationRegistry::inviteTexts) — no
 * roster, no entries, nobody's contact details.
 */
class InvitePage
{
    public function __construct(private FederationRegistry $registry) {}

    /** The `$e` every shared partial reads. */
    public function payload(FederationEvent $e, string $locale): array
    {
        $d = (array) $e->details;
        $tr = Translations::of($e, $locale);
        $fed = $e->federation;
        $ev = $this->registry->eventForInvite($e);
        $texts = $this->registry->inviteTexts($e)['en'];   // the pin and map link are language-free
        $currency = (string) ($ev['currency'] ?? 'BHD');

        $start = $this->moment($e->starts_on);
        $end = $this->moment($e->ends_on);
        $hasTime = fn (?string $v) => $v !== null && strlen($v) > 10;
        $dayStart = preg_match('/^\d{2}:\d{2}$/', (string) ($d['dayStart'] ?? '')) ? $d['dayStart'] : null;
        $dayEnd = preg_match('/^\d{2}:\d{2}$/', (string) ($d['dayEnd'] ?? '')) ? $d['dayEnd'] : null;
        $startClock = $dayStart ? Carbon::parse($dayStart) : ($start && $hasTime($e->starts_on) ? $start : null);
        $endClock = $dayEnd ? Carbon::parse($dayEnd) : ($end && $hasTime($e->ends_on) ? $end : null);

        $venue = trim(implode(', ', array_filter([
            $tr->get('venue', ($d['venue'] ?? '') !== 'TBC' ? (string) ($d['venue'] ?? '') : ''),
            $tr->get('city', (string) ($d['city'] ?? '')),
        ], fn ($v) => is_string($v) && trim($v) !== '')));

        $types = collect(array_values((array) ($d['types'] ?? [])))
            ->mapWithKeys(fn ($t, $i) => [(string) $t => (string) $tr->get('types.'.$i, (string) $t)])->all();

        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $fed?->color) ? $fed->color : '#1e2c4f';
        $icon = FederationBrand::favicon($fed);
        $going = (int) \App\Federations\Models\FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))
            ->where('status', 'Active')->count();
        $sportKey = strtolower((string) $e->sport);

        return [
            'id' => substr($e->uuid, 0, 8),
            'key' => $e->uuid,
            'uuid' => $e->uuid,
            'offered_locales' => Translations::offered($e),

            'title' => (string) $tr->get('title', (string) $e->name),
            'about' => (string) $tr->get('about', FederationEvent::aboutText($d['about'] ?? '')),
            'club' => (string) ($fed?->name ?? ''),
            'host' => (string) ($fed?->name ?? ''),
            'host_logo' => $fed && $fed->logo && $fed->isApproved() ? route('federations.logo', $fed->uuid).'?v='.substr(md5((string) $fed->logo), 0, 8) : null,
            'color' => $color,
            'icon' => 'bi-envelope-paper',
            'type' => __('federations::invite.type'),
            'sport' => $sportKey,
            'sport_label' => SportLabel::for($sportKey, (string) $e->sport),
            'sport_icon' => 'bi-trophy',
            'photo' => $this->registry->coverArt($e),
            'icons' => [192 => $icon, 180 => $icon],
            'manifest' => null,

            'day' => $start ? $start->format('d') : '—',
            'mon' => $start ? Cldr::skeleton($start, 'MMM', 'M') : '',
            'wday' => $start ? Cldr::skeleton($start, 'EEE', 'D') : '',
            'date_iso' => $start?->toDateString(),
            'date' => $start ? Cldr::fullDate($start) : null,
            'end_date' => $end && $start && ! $end->isSameDay($start) ? Cldr::fullDate($end) : null,
            'time' => $startClock ? Cldr::time($startClock) : __('events::events.tba'),
            'end' => $endClock ? Cldr::time($endClock) : '',

            'location' => $venue !== '' ? $venue : __('events::events.tba'),
            'address' => (string) $tr->get('location', (string) ($d['location'] ?? '')),
            'location_url' => ($texts['mapUrl'] ?? '') ?: null,
            'lat' => is_numeric($texts['mapLat'] ?? null) ? (float) $texts['mapLat'] : null,
            'lng' => is_numeric($texts['mapLng'] ?? null) ? (float) $texts['mapLng'] : null,

            'going' => $going,
            'cap' => (int) $e->capacity ?: max($going, 1),
            'capped' => (int) $e->capacity > 0,

            'participant_fee' => $this->headline($ev, $currency),
            'fee_is_paid' => collect($ev['fees'])->contains(fn ($f) => (float) $f['price'] > 0),
            'fees' => $this->fees($ev, $types, $currency, $d),
            'spectator' => null,

            'phases' => $this->phases($e, $d, $tr, $startClock, $endClock),
            'requirements' => $this->requirements($d, $tr),
            'tags' => [],
            'divisions' => [],
            'documents' => $this->documents($e),
            'ended' => $e->hasEnded(),
        ];
    }

    /**
     * The cover carousel's cards: every offered language, each already
     * rendered in itself — through CoverLanguages' one loop.
     */
    public function coverLanguages(FederationEvent $e, array $payload): array
    {
        $start = $this->moment($e->starts_on);
        $sportKey = strtolower((string) $e->sport);

        return CoverLanguages::forRecord($e, function (string $code) use ($e, $start, $sportKey, $payload) {
            $tr = Translations::of($e, $code);
            $sport = SportLabel::for($sportKey, (string) $e->sport);

            return [
                'tag' => \App\Events\Support\EventClassification::line($sport, __('federations::invite.type')),
                'ev_title' => (string) $tr->get('title', (string) $e->name),
                'ev_date' => trim(($start ? Cldr::skeleton($start, 'EEEdMMM', 'D j M') : '')
                    .(($payload['host'] ?? '') !== '' ? ' · '.$payload['host'] : '')),
            ];
        });
    }

    private function moment(?string $v): ?Carbon
    {
        return $v ? rescue(fn () => Carbon::parse($v), null, report: false) : null;
    }

    private function money(float $amount, string $currency): string
    {
        return EventFee::display($amount, $currency);
    }

    /** "From BHD 10", "Free", or a dash when the organiser priced nothing. */
    private function headline(array $ev, string $currency): string
    {
        $fees = collect($ev['fees']);
        if ($fees->isEmpty()) {
            return '—';
        }
        $paid = $fees->filter(fn ($f) => (float) $f['price'] > 0);
        if ($paid->isEmpty()) {
            return __('events::events.fee_free');
        }
        $min = (float) $paid->min('price');

        return $paid->count() > 1 && $paid->pluck('price')->unique()->count() > 1
            ? __('federations::invite.fee_from', ['amount' => $this->money($min, $currency)])
            : $this->money($min, $currency);
    }

    private function fees(array $ev, array $types, string $currency, array $d): array
    {
        $lines = collect($ev['fees'])->map(fn ($f) => [
            'label' => ($types[$f['type']] ?? $f['type']).' · '.($f['unit'] === 'team'
                ? __('federations::invite.per_team', ['n' => $f['teamSize'] ?? 3])
                : __('federations::invite.per_athlete')),
            'amount' => (float) $f['price'] > 0 ? $this->money((float) $f['price'], $currency) : __('events::events.fee_free'),
        ])->values()->all();

        $late = null;
        if ((float) $ev['lateFee'] > 0 && ($d['enrollEnd'] ?? '')) {
            $from = $this->moment(substr($d['enrollEnd'], 0, 10))?->addDay();
            $late = ['from' => $from ? Cldr::fullDate($from) : '', 'amount' => $this->money((float) $ev['lateFee'], $currency)];
        }

        return [
            'currency' => $currency,
            'participant' => $lines,
            'spectator' => [],
            'late' => $late,
            'any' => $lines !== [],
            'multiple' => count($lines) > 1,
        ];
    }

    /** The key dates, as the run-of-show timeline the detail card draws. */
    private function phases(FederationEvent $e, array $d, $tr, ?Carbon $startClock, ?Carbon $endClock): array
    {
        $rows = [];
        $at = function (string $label, ?string $when, ?string $time = null, ?string $note = null) use (&$rows) {
            $date = $this->moment($when ? substr($when, 0, 10) : null);
            if (! $date) {
                return;
            }
            $rows[] = ['label' => $label, 'date' => $date->toDateString(), 'time' => $time, 'note' => $note ?: null];
        };

        $at(__('federations::invite.date_opens'), $d['enrollStart'] ?? null);
        $at(__('federations::invite.date_deadline'), $d['enrollEnd'] ?? null, Cldr::time(Carbon::parse('23:59')));
        $weigh = $this->moment($d['weighIn'] ?? null);
        $at(__('federations::invite.date_weighin'), $d['weighIn'] ?? null,
            $weigh && strlen((string) $d['weighIn']) > 10 ? Cldr::time($weigh) : null,
            (string) $tr->get('location', (string) ($d['location'] ?? '')));
        $at(__('federations::invite.date_draw'), $d['drawDate'] ?? null);

        $start = $this->moment($e->starts_on);
        if ($start) {
            $end = $this->moment($e->ends_on);
            $hours = $startClock ? Cldr::time($startClock).($endClock ? ' – '.Cldr::time($endClock) : '') : null;
            $note = [];
            if ($end && ! $end->isSameDay($start)) {
                $note[] = __('federations::invite.date_until', ['date' => Cldr::fullDate($end)]);
            }
            if (! empty($d['breakOn']) && preg_match('/^\d{2}:\d{2}$/', (string) ($d['breakFrom'] ?? ''))) {
                $note[] = __('federations::invite.date_break', ['from' => Cldr::time(Carbon::parse($d['breakFrom'])),
                    'to' => preg_match('/^\d{2}:\d{2}$/', (string) ($d['breakTo'] ?? '')) ? Cldr::time(Carbon::parse($d['breakTo'])) : '']);
            }
            $rows[] = ['label' => __('federations::invite.date_competition'), 'date' => $start->toDateString(), 'time' => $hours, 'note' => implode(' · ', $note) ?: null];
        }

        usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $rows;
    }

    private function requirements(array $d, $tr): array
    {
        $out = [];
        foreach (array_values((array) ($d['reqs'] ?? [])) as $i => $line) {
            if (is_string($line) && trim($line) !== '') {
                $out[] = (string) $tr->get('requirements.'.$i, mb_substr(trim($line), 0, 300));
            }
        }

        return $out;
    }

    /** The federation's attachments, in the shape <x-event-documents> draws (public for the linked poster). */
    public function documentsOf(FederationEvent $e): array
    {
        return $this->documents($e);
    }

    /** The organiser's files, in the shape <x-event-documents> draws. */
    private function documents(FederationEvent $e): array
    {
        return $e->documents->map(function ($doc) {
            $ext = strtolower(pathinfo((string) $doc->filename, PATHINFO_EXTENSION)) ?: 'file';
            $size = (int) $doc->size;

            return [
                'uuid' => $doc->uuid,
                'title' => (string) $doc->title,
                'extension' => $ext,
                'size' => $size,
                'size_label' => $size >= 1048576 ? number_format($size / 1048576, 1).' MB' : max(1, (int) round($size / 1024)).' KB',
                'icon' => match ($ext) {
                    'pdf' => 'bi-file-earmark-pdf-fill',
                    'doc', 'docx' => 'bi-file-earmark-word-fill',
                    'xls', 'xlsx' => 'bi-file-earmark-excel-fill',
                    'jpg', 'jpeg', 'png', 'webp' => 'bi-file-earmark-image-fill',
                    default => 'bi-file-earmark-fill',
                },
                'url' => route('federations.event-doc', $doc->uuid),
            ];
        })->values()->all();
    }
}
