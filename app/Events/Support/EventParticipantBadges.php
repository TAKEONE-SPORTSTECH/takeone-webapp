<?php

namespace App\Events\Support;

use Ahl\Certificates\CertificateService;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The competitors' badges of an event (owner, 2026-10-15) — the officials' badge
 * (EventBadges), drawn for the people who compete: one badge per person, however
 * many divisions they entered, saying their division(s) and their club.
 *
 * Everything else is the officials' badge: the same A6 page, the same design room,
 * the same host-default-or-this-event design, the same print-shop sheets. It answers
 * to its own alias and type, so its design is its own. Its QR opens the event's page.
 */
class EventParticipantBadges extends EventBadges
{
    public const ALIAS = 'event_participant_badges';

    public const TYPE = 'participant_badge';

    public function alias(): string
    {
        return self::ALIAS;
    }

    public function placeholderKeys(): array
    {
        return ['name', 'role', 'club', 'event', 'nationality'];
    }

    public function eligible(Model $context): array
    {
        return ['participants' => $context instanceof ClubEvent ? $this->participants($context)->count() : 0];
    }

    /**
     * The event's competitors, one row per person: their entries' divisions
     * joined, the club they compete for.
     *
     * @return Collection<int, array{user: \App\Members\Models\User, divisions: string, club: ?string, photo: ?string}>
     */
    public function participants(ClubEvent $event): Collection
    {
        $entries = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->with(['user', 'category:id,name', 'representingTenant:id,club_name'])->orderBy('id')->get()
            ->filter(fn ($r) => $r->user !== null);
        $clubs = EntryClub::forMany($event, $entries->pluck('id'));

        return $entries->groupBy('user_id')->map(function ($rows) use ($clubs) {
            $first = $rows->first();

            return [
                'user' => $first->user,
                'divisions' => $rows->map(fn ($r) => $r->category ? (string) ($r->category->getRawOriginal('name') ?: $r->category->name) : null)->filter()->unique()->implode(' · '),
                'club' => $first->representingTenant?->club_name ?: ($clubs[$first->id]['name'] ?? null),
                'photo' => $rows->pluck('photo')->filter()->first(),
                // The entry the club is read from, for the club's crest on the badge.
                'entry' => $first,
            ];
        })->sortBy(fn ($p) => mb_strtolower((string) ($p['user']->full_name ?: $p['user']->name)))->values();
    }

    /** What one competitor's badge prints. {role} is their division(s). */
    public function participantFields(array $p, ClubEvent $event): array
    {
        $user = $p['user'];
        $photo = $this->face($p['photo']) ?: ($user->profile_picture ? $this->face($user->profile_picture) : null);

        return [
            'name' => (string) ($user->full_name ?: $user->name ?: ''),
            'role' => $p['divisions'] !== '' ? $p['divisions'] : __('cert::messages.types.participant_badge', [], $this->locale($event)),
            'club' => $p['club'],
            'event' => $this->headline($event),
            'nationality' => $this->nationalityOf($user),
            'photo' => $photo,
            // The QR opens the event's page, as on the officials' badges.
            'qr' => $this->eventPageUrl($event),
            'certificate_no' => strtoupper(substr(sha1($event->uuid.'|'.$user->uuid), 0, 8)),
        ];
    }

    public function sampleFields(ClubEvent $event, string $locale): array
    {
        $first = $this->participants($event)->first();
        if ($first) {
            return $this->participantFields($first, $event);
        }

        return [
            'name' => __('certificates::certificates.sample_name', [], $locale),
            'role' => __('cert::messages.sample_division', [], $locale),
            'club' => EventHost::of($event)->name ?: null,
            'event' => $this->headline($event),
            'nationality' => null,
            'photo' => null,
            'qr' => $this->eventPageUrl($event),
            'certificate_no' => 'SAMPLE',
        ];
    }

    /** Every competitor's badge, four to an A4 sheet — the file a print shop is sent. */
    public function sheets(ClubEvent $event, string $locale, ?callable $progress = null, ?string $type = null): string
    {
        if ($design = $this->badgeDesign($event)) {
            $pages = $this->participantsFor($event, $type)->map(fn ($p) => [
                $design,
                $this->designValues($event, $this->participantFields($p, $event), $p['entry'] ? $this->entryCrest($event, $p['entry']) : null,
                    $p['user']->nationality, $p['entry'] ? $this->entryClub($event, $p['entry']) : null),
            ])->all();

            return \App\Printables\Printer::sheets($pages, $progress);
        }
        $service = app(CertificateService::class);
        $template = $this->template($event);
        $pages = $this->participantsFor($event, $type)->map(fn ($p) => fn () => $service->renderDocument($template, $locale, $this->participantFields($p, $event), $event))->all();

        return $service->bundlePages($pages, $progress);
    }

    public function signature(ClubEvent $event, string $locale, ?string $type = null): string
    {
        $ids = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->orderBy('id')
            ->get(['id', 'updated_at', 'category_id'])->map(fn ($r) => $r->id.'@'.$r->updated_at.'@'.$r->category_id)->implode(',');

        return substr(sha1($locale.'|'.(string) $type.'|'.$ids.'|'.$this->template($event)->updated_at.'|'.$this->designSignature($event)), 0, 16);
    }

    public function printCount(Model $context, string $type): int
    {
        if (! $context instanceof ClubEvent || explode('.', $type, 2)[0] !== self::TYPE) {
            return 0;
        }

        return $this->participantsFor($context, $type)->count();
    }

    /**
     * The competitors one bundle prints: everyone, or just the ticked few when the
     * type names a remembered selection (`participant_badge.sel-<token>`).
     */
    public function participantsFor(ClubEvent $event, ?string $type = null): Collection
    {
        [, $job] = array_pad(explode('.', (string) $type, 2), 2, null);
        $all = $this->participants($event);
        if ($type === null || $job === null || ! str_starts_with((string) $job, 'sel-')) {
            return $all;
        }
        $ids = app(\App\Certificates\Support\CertificateBundles::class)->selected($this, $event, substr((string) $job, 4));

        return $ids === null ? $all : $all->filter(fn ($p) => in_array((int) $p['user']->id, $ids, true))->values();
    }

    /** The ticked competitors, as the ids a selection bundle is remembered by. */
    public function pageSelection(Model $context, array $holders): array
    {
        if (! $context instanceof ClubEvent || $holders === []) {
            return [];
        }
        $want = array_fill_keys(array_map('strval', $holders), true);

        return $this->participants($context)
            ->filter(fn ($p) => isset($want[$this->holderKey($p['user'])]))
            ->map(fn ($p) => (int) $p['user']->id)->unique()->values()->all();
    }

    /** Competitors hold no job, so a participant badge bundle is never split by one. */
    public function bundleRecipients(Model $context, string $type, string $job): ?array
    {
        return null;
    }
}
