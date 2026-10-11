<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventDrawPin;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * The draw, on a SCORING TABLET — the device's side of it.
 *
 * ── One door for every sport ──────────────────────────────────────────────
 * A tablet beside a mat is paired through its sport's own device table
 * (Karate, Taekwondo, BJJ — the triplication CLAUDE.md lists as debt). This
 * class is the ONE place that knows all three, so the draw door is written
 * once and a fourth sport is one line in DEVICES, not a fourth copy of the
 * feature (CLAUDE.md → Shared Stays Shared).
 *
 * A tablet qualifies on exactly the terms its scoring console opens on:
 * claimed, paired to an event of its sport, a CONTROL surface on a mat, and
 * paired by somebody who may still score that event. Anything else is
 * "nothing here" — one answer for every failure.
 *
 * ── Reading vs editing ────────────────────────────────────────────────────
 * Reading the draw rides on the pairing, like the console does: the tablet
 * reads AS the organiser who paired it.
 *
 * Editing does NOT. The pairing proves which device this is, not who is
 * holding it, and a draw is the organiser's. So editing needs the event's
 * tablet PIN (EventDrawPin): a right PIN opens a short-lived grant in THIS
 * tablet's session, and every edit made under it is made as — and recorded
 * against — the organiser who set the PIN, re-checked on every request.
 */
class TabletDraw
{
    /** How long a right PIN keeps the draw editable, sliding with each edit. */
    public const GRANT_MINUTES = 10;

    /**
     * device class => [event sport, console route name]
     *
     * @var array<class-string<Model>, array{0: string, 1: string}>
     */
    private const DEVICES = [
        \App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice::class => ['karate', 'karate-scoreboard.token-control'],
        \App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice::class => ['taekwondo', 'taekwondo-scoreboard.token-control'],
        \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice::class => ['bjj', 'bjj-scoreboard.token-control'],
    ];

    public function __construct(
        public readonly Model $device,
        public readonly ClubEvent $event,
        public readonly User $pairedBy,
        public readonly string $consoleUrl,
        private readonly string $token,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('events.tablet_draw');
    }

    /** Does editing on the tablet need the event's PIN? (events.tablet_draw_pin) */
    public static function pinRequired(): bool
    {
        return (bool) config('events.tablet_draw_pin');
    }

    /** The tablet behind this token, or null for every way that can fail. */
    public static function resolve(?string $token): ?self
    {
        if (! is_string($token) || strlen($token) !== 40 || ! ctype_alnum($token)) {
            return null;
        }

        foreach (self::DEVICES as $class => [$sport, $consoleRoute]) {
            if (! class_exists($class)) {
                continue;
            }

            $device = $class::resolve($token);

            if (! $device) {
                continue;
            }

            $event = $device->event;

            if (! $device->isClaimed() || ! $event || $device->surface !== 'control' || ! $device->court
                || $event->sport !== $sport || $event->is_archived) {
                return null;
            }

            $by = $device->created_by ? User::find($device->created_by) : null;

            if (! $by || ! app(EventAccess::class)->canScore($event, $by)) {
                return null;
            }

            return new self($device, $event, $by, Route::has($consoleRoute) ? route($consoleRoute, $token) : url('/screen'), $token);
        }

        return null;
    }

    // ── The edit grant ───────────────────────────────────────────────────

    private function grantKey(): string
    {
        return 'tablet_draw.'.hash('sha256', $this->token);
    }

    /** The organiser whose PIN unlocked this tablet, while the grant lasts. */
    public function editor(): ?User
    {
        // No PIN: the tablet edits as the organiser who paired it, while they
        // may still arrange this draw. Pairing was the trust; this keeps it
        // honest every request rather than for the life of the pairing.
        if (! self::pinRequired()) {
            return app(EventAccess::class)->canArrange($this->event, $this->pairedBy) ? $this->pairedBy : null;
        }

        $grant = session($this->grantKey());

        if (! is_array($grant) || (int) ($grant['event'] ?? 0) !== $this->event->id
            || (int) ($grant['until'] ?? 0) < now()->timestamp) {
            return null;
        }

        $pin = EventDrawPin::where('event_id', $this->event->id)->first();

        // The PIN changed or was removed since: the grant goes with it.
        if (! $pin || (int) $pin->user_id !== (int) ($grant['user'] ?? 0)
            || ! hash_equals(self::fingerprint($pin), (string) ($grant['pin'] ?? ''))) {
            return null;
        }

        $owner = $pin->owner;

        return $owner && app(EventAccess::class)->canArrange($this->event, $owner) ? $owner : null;
    }

    public function grant(EventDrawPin $pin): void
    {
        session([$this->grantKey() => [
            'event' => $this->event->id,
            'user' => (int) $pin->user_id,
            'pin' => self::fingerprint($pin),
            'until' => now()->addMinutes(self::GRANT_MINUTES)->timestamp,
        ]]);
    }

    /**
     * Changes only when the PIN itself changes — never with a try, right or
     * wrong — so setting a new PIN ends every grant the old one opened.
     */
    private static function fingerprint(EventDrawPin $pin): string
    {
        return substr(hash('sha256', (string) $pin->getRawOriginal('pin_hash')), 0, 24);
    }

    /** Each edit buys another few minutes — a desk mid-repair is not cut off. */
    public function extend(): void
    {
        $grant = session($this->grantKey());

        if (is_array($grant)) {
            $grant['until'] = now()->addMinutes(self::GRANT_MINUTES)->timestamp;
            session([$this->grantKey() => $grant]);
        }
    }

    public function revoke(): void
    {
        session()->forget($this->grantKey());
    }

    /** Seconds left on the grant, for the countdown on the tablet. */
    public function secondsLeft(): int
    {
        $grant = session($this->grantKey());

        return is_array($grant) ? max(0, (int) ($grant['until'] ?? 0) - now()->timestamp) : 0;
    }
}
