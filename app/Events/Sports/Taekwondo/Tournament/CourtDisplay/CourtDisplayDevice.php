<?php

namespace App\Events\Sports\Taekwondo\Tournament\CourtDisplay;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A paired hall screen — the Raspberry Pi's whole identity.
 *
 * The device never signs in. Nobody stands at a wall-mounted screen to type a
 * password, and a session on an unattended machine in a public hall would be a
 * worse thing to own than the board itself. Instead the screen holds a random
 * token that says exactly one thing: "I am the display for this event's Mat 1."
 *
 * What that buys, and why it is not just a password with extra steps:
 *
 *  · It is scoped to one board. The token cannot read another mat, another
 *    event, the entry list, or anything a member could see.
 *  · It is read-only. There is no write path behind it at all.
 *  · It is revocable in one click, without disturbing any other screen.
 *  · It is stored hashed, so the database row cannot be replayed as a device.
 *
 * The plaintext exists exactly once, at pairing, and goes straight to the Pi.
 */
class CourtDisplayDevice extends Model
{
    protected $table = 'court_displays';

    protected $fillable = ['event_id', 'court', 'surface', 'token_hash', 'token_hint', 'pairing_code', 'label', 'created_by'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'claimed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Hidden because it must never ride along in a JSON response or a log line
     * by accident. Nothing in the app needs to read it except verification.
     */
    protected $hidden = ['token_hash', 'token_hint'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /**
     * Pair a new screen and hand back the one plaintext token that will ever
     * exist for it.
     *
     * @return array{device: self, token: string}
     */
    public static function issue(ClubEvent $event, string $court, ?int $createdBy = null, ?string $label = null): array
    {
        // 40 chars of base62 — far past guessing, and safe in a URL and a QR.
        $token = Str::random(40);

        $device = static::create([
            'event_id' => $event->id,
            'court' => $court,
            'token_hash' => static::hash($token),
            'token_hint' => substr($token, 0, 6),
            'label' => $label,
            'created_by' => $createdBy,
        ]);

        // Issued already knowing its mat — it skips the pairing screen.
        $device->forceFill(['claimed_at' => now()])->save();

        return ['device' => $device, 'token' => $token];
    }

    /**
     * A brand-new screen that does not yet know which mat it is.
     *
     * This is what a Pi does on first boot: it asks for an identity, stores the
     * token, and then stands there showing its pairing code until somebody
     * claims it. No event, no court, nothing to display yet.
     *
     * @return array{device: self, token: string}
     */
    public static function begin(?string $label = null): array
    {
        $token = Str::random(40);

        $device = static::create([
            'token_hash' => static::hash($token),
            'token_hint' => substr($token, 0, 6),
            // Read aloud across a hall and typed by hand when a camera will not
            // focus, so: no vowels (no accidental words), and no 0/O/1/I.
            'pairing_code' => static::freshPairingCode(),
            'label' => $label,
        ]);

        return ['device' => $device, 'token' => $token];
    }

    /** An unambiguous 6-character code that is not already in use. */
    private static function freshPairingCode(): string
    {
        $alphabet = 'BCDFGHJKLMNPQRSTVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('pairing_code', $code)->exists());

        return $code;
    }

    /**
     * Guarantee this screen has a code to show.
     *
     * A device can arrive back at the pairing screen without one — its event was
     * deleted, or it was reset — and a QR encoding nothing would leave a Pi
     * stuck with no way back other than a keyboard it does not have.
     */
    public function ensurePairable(): void
    {
        if (! $this->pairing_code) {
            $this->forceFill(['pairing_code' => static::freshPairingCode()])->save();
        }
    }

    /** True once an organiser has told this screen which board it shows. */
    public function isClaimed(): bool
    {
        return $this->event_id !== null && $this->court !== null;
    }

    /**
     * Assign this screen to a mat. The caller must already have checked that
     * the acting user may manage the event — this method does not know who is
     * asking, and must never be reached without that check.
     */
    public function claim(ClubEvent $event, string $court, ?int $by = null, ?string $surface = null): void
    {
        $this->forceFill([
            'event_id' => $event->id,
            'court' => $court,
            // What this screen is FOR, chosen by whoever paired it. Null means
            // follow the mat, which is what a board hanging over it should do.
            'surface' => in_array($surface, ['queue', 'bout', 'control'], true) ? $surface : null,
            'claimed_at' => now(),
            'created_by' => $this->created_by ?: $by,
            // Spent: the code on the wall stops being claimable the moment it
            // is used, so a photograph of the screen is worth nothing after.
            'pairing_code' => null,
        ])->save();
    }

    /**
     * The live device holding this token, or null.
     *
     * Looks up BY HASH, so the lookup is a single indexed read with no
     * plaintext comparison anywhere — and a revoked screen resolves to nothing
     * from the moment it is revoked, without needing to reach the device.
     */
    public static function resolve(?string $token): ?self
    {
        if (! is_string($token) || strlen($token) !== 40 || ! ctype_alnum($token)) {
            return null;
        }

        return static::query()
            ->where('token_hash', static::hash($token))
            ->whereNull('revoked_at')
            ->with('event')
            ->first();
    }

    /**
     * The unclaimed screen advertising this pairing code, or null.
     *
     * Only ever matches a screen that is genuinely waiting to be told what it
     * is: a claimed or revoked device is not pairable, so a code photographed
     * off a wall an hour ago resolves to nothing.
     */
    public static function pairable(?string $code): ?self
    {
        if (! is_string($code) || ! preg_match('/^[A-Z0-9]{6}$/', $code)) {
            return null;
        }

        return static::query()
            ->where('pairing_code', $code)
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * SHA-256, deliberately not a password hash.
     *
     * bcrypt/argon are slow BY DESIGN to blunt guessing at low-entropy human
     * passwords. This token is 40 random characters — guessing is already
     * impossible — and the board is polled continuously by every screen in the
     * hall, so a deliberately slow hash here would only be a way to exhaust the
     * server. Fast digest over high entropy is the right trade.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * What an organiser's console may know about this screen.
     *
     * Deliberately not `toArray()`: that would carry the token hint, the row id
     * of whoever created it and the raw timestamps. A console needs to tell one
     * screen from another and see whether it is alive — nothing more.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label ?: null,
            'court' => $this->court,
            // What it was paired AS. A panel that only says "Mat 1" cannot tell
            // an organiser which of the three screens on Mat 1 is the one
            // scoring — and that is the row they most need to find.
            'surface' => $this->surface ?: 'follow',
            'surface_label' => __('personal.event_screens_surface_'.($this->surface ?: 'follow')),
            // "alive" is a stronger claim than "was seen once", and it is only
            // answerable because the board heartbeats.
            //
            // Ten minutes for a beat asked for every sixty seconds, because the
            // appliance browser does not honour that interval: cog/WPE on DRM
            // throttles background timers hard — measured at ~2m50s for a 60s
            // interval on a Pi 3B, and ~2m45s for the pairing screen's 5s poll.
            // A window near the nominal period would flap green/amber on a
            // perfectly healthy screen, which is worse than saying nothing. Ten
            // minutes still catches the case that matters: a screen unplugged or
            // off the network while the hall fills up.
            'live' => $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(10)),
            'last_seen' => $this->last_seen_at?->diffForHumans(),
        ];
    }

    /** Note that the screen is alive, without writing a row on every poll. */
    public function touchSeen(): void
    {
        if (! $this->last_seen_at || $this->last_seen_at->lt(now()->subMinute())) {
            $this->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->saveQuietly();
    }

    /**
     * Send this screen back to its pairing code, keeping its identity.
     *
     * The console's "unpair" — and deliberately NOT revoke(). A revoked token
     * resolves to nothing, and the Pi agent only ever enrols when its token file
     * is empty: it would sit on a 404 forever, recoverable only by editing the
     * SD card. Unclaiming keeps the token valid, so the device polls, sees it is
     * no longer claimed, and comes back showing a fresh code — which is the
     * thing an organiser moving a screen between mats actually wants.
     *
     * A new code every time, because the old one was spent when it was claimed
     * and a code that came back would let an onlooker's photograph work twice.
     */
    public function unclaim(): void
    {
        $this->forceFill([
            'event_id' => null,
            'court' => null,
            'claimed_at' => null,
            'pairing_code' => static::freshPairingCode(),
        ])->save();
    }
}
