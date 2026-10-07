<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventFeatureRequest;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Platform\Models\PlatformSetting;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Paid featuring on /me/events — the one place its rules live (2026-09-28).
 *
 *   organiser asks → uploads proof of the platform's flat fee → super-admin
 *   approves (or rejects with a reason) → the event is in the spotlight until
 *   it ends. An approved feature can be revoked. No gateway (CLAUDE.md →
 *   Payment Gateway): the proof is a picture or a PDF, checked by its bytes.
 *
 * A subject is a club event (ClubEvent) or a federation's (FederationEvent).
 * This class decides nothing about WHO may ask — the calling controller
 * authorises the organiser first (EventAccess::canManage, or the federation
 * manager check); every decision method re-checks that the actor is a
 * super-admin itself.
 */
class EventFeaturing
{
    public const FEE_KEY = 'event_feature_fee';
    public const CURRENCY_KEY = 'event_feature_currency';

    /** What a proof may be. Office files are refused: a receipt is a picture or a PDF. */
    private const PROOF_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public const MAX_PROOF_BYTES = 10 * 1024 * 1024;

    // ─── The fee ──────────────────────────────────────────────────────────

    /** The platform's flat fee, or null while the super-admin has not set one (featuring closed). */
    public static function fee(): ?array
    {
        $raw = PlatformSetting::get(self::FEE_KEY);
        if ($raw === null || $raw === '' || ! is_numeric($raw) || (float) $raw <= 0) {
            return null;
        }
        $amount = round((float) $raw, 3);
        $currency = self::currency();

        return ['amount' => $amount, 'currency' => $currency, 'display' => self::display($amount, $currency)];
    }

    public static function currency(): string
    {
        $c = strtoupper((string) PlatformSetting::get(self::CURRENCY_KEY, 'BHD'));

        return preg_match('/^[A-Z]{3}$/', $c) ? $c : 'BHD';
    }

    /** Null closes featuring to new requests; already approved ones stay featured. */
    public static function setFee(?float $amount, string $currency): void
    {
        PlatformSetting::set(self::FEE_KEY, $amount !== null && $amount > 0 ? (string) round($amount, 3) : '');
        PlatformSetting::set(self::CURRENCY_KEY, preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'BHD');
    }

    public static function display(float $amount, string $currency): string
    {
        return EventFee::display($amount, $currency);
    }

    // ─── What an event's featuring looks like now ─────────────────────────

    /** The latest request for one event, or null. */
    public static function latest(ClubEvent|FederationEvent $subject): ?EventFeatureRequest
    {
        return self::forSubject(EventFeatureRequest::query(), $subject)->latest('id')->first();
    }

    /**
     * The organiser's view: the state, the fee, and whether they may ask now.
     * Never carries the proof path or who decided.
     */
    public static function state(ClubEvent|FederationEvent $subject): array
    {
        $r = self::latest($subject);
        $fee = self::fee();
        $ended = self::ended($subject);
        $status = $r?->status ?? 'none';
        $open = in_array($status, ['none', EventFeatureRequest::REJECTED, EventFeatureRequest::REVOKED], true);

        return [
            'status' => $status,
            'featured' => $status === EventFeatureRequest::APPROVED && ! $ended,
            'reason' => in_array($status, [EventFeatureRequest::REJECTED, EventFeatureRequest::REVOKED], true) ? $r?->reason : null,
            'paid' => $r ? self::display((float) $r->amount, (string) $r->currency) : null,
            'submitted_at' => $r?->created_at?->toIso8601String(),
            'fee' => $fee,
            'ended' => $ended,
            // Offered only when a fee is set, the event is still to come, and
            // nothing is waiting or already live. The endpoint re-checks all three.
            'can_request' => $open && $fee !== null && ! $ended,
        ];
    }

    public static function ended(ClubEvent|FederationEvent $subject): bool
    {
        return $subject->hasEnded();
    }

    /** @return Collection<int, int> club event ids with a live, approved feature, out of $ids */
    public static function featuredClubEventIds(iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->values();

        return $ids->isEmpty() ? collect() : EventFeatureRequest::whereIn('club_event_id', $ids)
            // Never a HIDDEN event in the spotlight, approved or not.
            ->whereIn('club_event_id', ClubEvent::listed()->select('id'))
            ->where('status', EventFeatureRequest::APPROVED)->pluck('club_event_id')->unique()->values();
    }

    /** @return Collection<int, int> federation event ids with an approved feature, out of $ids */
    public static function featuredFederationEventIds(iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->values();

        return $ids->isEmpty() ? collect() : EventFeatureRequest::whereIn('federation_event_id', $ids)
            ->where('status', EventFeatureRequest::APPROVED)->pluck('federation_event_id')->unique()->values();
    }

    // ─── The organiser asks ───────────────────────────────────────────────

    /**
     * Store the proof and open a pending request. The caller has already
     * authorised $user to manage $subject.
     *
     * @return array{ok: bool, message: string, state?: array}
     */
    public static function submit(User $user, ClubEvent|FederationEvent $subject, UploadedFile $proof): array
    {
        $fee = self::fee();
        if ($fee === null) {
            return ['ok' => false, 'message' => __('events::events.feature_closed')];
        }
        if (self::ended($subject)) {
            return ['ok' => false, 'message' => __('events::events.feature_ended')];
        }

        $info = $proof->getSize() <= self::MAX_PROOF_BYTES ? app(DocumentUpload::class)->inspect($proof) : null;
        if ($info === null || ! in_array($info['mime'], self::PROOF_MIMES, true)) {
            return ['ok' => false, 'message' => __('events::events.feature_bad_proof')];
        }

        $request = DB::transaction(function () use ($user, $subject, $fee, $proof) {
            // One live request per event. Checked inside the transaction so a
            // double tap cannot open two.
            $busy = self::forSubject(EventFeatureRequest::query(), $subject)
                ->whereIn('status', [EventFeatureRequest::PENDING, EventFeatureRequest::APPROVED])->lockForUpdate()->exists();
            if ($busy) {
                return null;
            }

            $stored = app(DocumentUpload::class)->store($proof, self::folder($subject), 'local');
            if ($stored === null) {
                return null;
            }

            $r = new EventFeatureRequest;
            $r->forceFill([
                'club_event_id' => $subject instanceof ClubEvent ? $subject->id : null,
                'federation_event_id' => $subject instanceof ClubEvent ? null : $subject->id,
                'requested_by' => $user->id,
                'amount' => $fee['amount'],
                'currency' => $fee['currency'],
                'proof_path' => $stored['path'],
                'proof_mime' => $stored['mime'],
                'status' => EventFeatureRequest::PENDING,
            ])->save();

            return $r;
        });

        if ($request === null) {
            return ['ok' => false, 'message' => __('events::events.feature_already')];
        }

        Log::info('event-feature: requested', ['request' => $request->uuid, 'user' => $user->id]);
        self::notifyAdmins($request, $subject, $user);

        return ['ok' => true, 'message' => __('events::events.feature_sent'), 'state' => self::state($subject)];
    }

    // ─── The super-admin decides ──────────────────────────────────────────

    /** @return array{ok: bool, message: string} */
    public static function approve(User $admin, EventFeatureRequest $r): array
    {
        return self::decide($admin, $r, EventFeatureRequest::PENDING, EventFeatureRequest::APPROVED, null);
    }

    /** @return array{ok: bool, message: string} */
    public static function reject(User $admin, EventFeatureRequest $r, string $reason): array
    {
        return self::decide($admin, $r, EventFeatureRequest::PENDING, EventFeatureRequest::REJECTED, $reason);
    }

    /** Take an approved feature down again. @return array{ok: bool, message: string} */
    public static function revoke(User $admin, EventFeatureRequest $r, string $reason): array
    {
        return self::decide($admin, $r, EventFeatureRequest::APPROVED, EventFeatureRequest::REVOKED, $reason);
    }

    private static function decide(User $admin, EventFeatureRequest $r, string $from, string $to, ?string $reason): array
    {
        if (! $admin->hasRole('super-admin')) {
            return ['ok' => false, 'message' => __('events::events.feature_not_allowed')];
        }

        $reason = $reason !== null ? mb_substr(trim(strip_tags($reason)), 0, 500) : null;
        // Compare-and-set: two admins deciding at once cannot both win.
        $changed = EventFeatureRequest::whereKey($r->id)->where('status', $from)->update([
            'status' => $to,
            'reason' => $reason ?: null,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $changed) {
            return ['ok' => false, 'message' => __('events::events.feature_already_decided')];
        }

        $r->refresh();
        Log::info('event-feature: '.$to, ['request' => $r->uuid, 'admin' => $admin->id]);
        self::notifyRequester($r, $admin);

        return ['ok' => true, 'message' => __('events::events.feature_decided_'.$to)];
    }

    // ─── Reading requests (admin) ─────────────────────────────────────────

    /** A request as the admin list draws it. */
    public static function row(EventFeatureRequest $r): array
    {
        $subject = self::subject($r);

        return [
            'uuid' => $r->uuid,
            'status' => $r->status,
            'kind' => $r->club_event_id ? 'club' : 'federation',
            'title' => $subject?->title ?? $subject?->name ?? __('events::events.feature_missing_event'),
            'owner' => $subject instanceof ClubEvent ? ($subject->host()->name ?: null)
                : ($subject?->federation?->name ?? null),
            'date' => $subject instanceof ClubEvent ? $subject->date?->toDateString() : ($subject?->starts_on ? substr((string) $subject->starts_on, 0, 10) : null),
            'ended' => $subject ? self::ended($subject) : true,
            'amount' => self::display((float) $r->amount, (string) $r->currency),
            'requester' => $r->requester?->full_name ?: $r->requester?->name,
            'requested_at' => $r->created_at?->toIso8601String(),
            'reason' => $r->reason,
            'decided_by' => $r->decider?->full_name ?: $r->decider?->name,
            'decided_at' => $r->decided_at?->toIso8601String(),
            'proof_url' => $r->proof_path ? route('admin.platform.featured-events.proof', $r->uuid) : null,
            'proof_is_pdf' => $r->proof_mime === 'application/pdf',
            'event_url' => $subject instanceof ClubEvent ? route('me.events.show', $subject->uuid)
                : ($subject && \Illuminate\Support\Facades\Route::has('federations.app.invitation')
                    ? route('federations.app.invitation').'?event='.rawurlencode($subject->uuid) : null),
        ];
    }

    public static function subject(EventFeatureRequest $r): ClubEvent|FederationEvent|null
    {
        if ($r->club_event_id) {
            return $r->clubEvent;
        }

        return $r->federation_event_id && class_exists(FederationEvent::class)
            ? FederationEvent::with('federation')->find($r->federation_event_id) : null;
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private static function forSubject($query, ClubEvent|FederationEvent $subject)
    {
        return $subject instanceof ClubEvent
            ? $query->where('club_event_id', $subject->id)
            : $query->where('federation_event_id', $subject->id);
    }

    /** Owner-first, built from public ids (CLAUDE.md → Upload Storage Structure). */
    private static function folder(ClubEvent|FederationEvent $subject): string
    {
        return $subject instanceof ClubEvent
            ? StoragePath::event($subject, 'featuring')
            : 'federations/'.$subject->federation->uuid.'/events/'.$subject->uuid.'/featuring';
    }

    private static function title(ClubEvent|FederationEvent|null $subject): string
    {
        return (string) ($subject?->title ?? $subject?->name ?? '');
    }

    private static function notifyAdmins(EventFeatureRequest $r, ClubEvent|FederationEvent $subject, User $by): void
    {
        rescue(function () use ($r, $subject, $by) {
            $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id');
            foreach ($admins as $id) {
                UserNotification::notifyUser((int) $id, 'event_feature_requested', __('events::events.feature_notify_admin_title'), [
                    'actor_id' => $by->id,
                    'body' => __('events::events.feature_notify_admin_body', ['event' => self::title($subject), 'amount' => self::display((float) $r->amount, (string) $r->currency)]),
                    'action_url' => route('admin.platform.featured-events'),
                    'icon' => 'bi-stars',
                ]);
            }
        }, report: true);
    }

    private static function notifyRequester(EventFeatureRequest $r, User $admin): void
    {
        if (! $r->requested_by) {
            return;
        }
        rescue(function () use ($r, $admin) {
            $subject = self::subject($r);
            $url = $subject instanceof ClubEvent ? route('me.events.manage', $subject->uuid)
                : (\Illuminate\Support\Facades\Route::has('federations.home') ? route('federations.home') : null);
            UserNotification::notifyUser((int) $r->requested_by, 'event_feature_'.$r->status, __('events::events.feature_notify_'.$r->status.'_title'), [
                'actor_id' => $admin->id,
                'body' => __('events::events.feature_notify_'.$r->status.'_body', ['event' => self::title($subject), 'reason' => (string) $r->reason]),
                'action_url' => $url,
                'icon' => 'bi-stars',
            ]);
        }, report: true);
    }
}
