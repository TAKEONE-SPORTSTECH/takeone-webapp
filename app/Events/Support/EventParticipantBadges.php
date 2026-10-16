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
    public function sheets(ClubEvent $event, string $locale, ?callable $progress = null): string
    {
        $service = app(CertificateService::class);
        $template = $this->template($event);
        $pages = $this->participants($event)->map(fn ($p) => fn () => $service->renderDocument($template, $locale, $this->participantFields($p, $event), $event))->all();

        return $service->bundlePages($pages, $progress);
    }

    public function signature(ClubEvent $event, string $locale): string
    {
        $ids = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->orderBy('id')
            ->get(['id', 'updated_at', 'category_id'])->map(fn ($r) => $r->id.'@'.$r->updated_at.'@'.$r->category_id)->implode(',');

        return substr(sha1($locale.'|'.$ids.'|'.$this->template($event)->updated_at), 0, 16);
    }

    public function printCount(Model $context, string $type): int
    {
        return $context instanceof ClubEvent && $type === self::TYPE ? $this->participants($context)->count() : 0;
    }
}
