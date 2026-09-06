<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One phone filming one mat.
 *
 * It holds a token and nothing else — no session, no account. A phone clamped
 * to a tripod beside a mat is handed between officials all day and left
 * unattended between bouts, so signing in on it would put somebody's whole
 * account in the hall. The token says exactly one thing: "I am angle 2 on Mat 1
 * of this event."
 *
 * What that buys:
 *  · It can be told to roll and to stop, and nothing else.
 *  · It can report its own storage, and file its own clips. It cannot read the
 *    draw, the entries, or another mat.
 *  · It is revocable in one click, without disturbing the other three cameras.
 *  · It is stored hashed, so the row cannot be replayed as a device.
 *
 * The plaintext exists once, at enrolment, and goes straight to the phone.
 * Modelled deliberately on CourtDisplayDevice — a camera and a screen are the
 * same KIND of thing (an unattended device with one job), and the two should
 * not drift into two different security stories.
 */
class EventCamera extends Model
{
    protected $fillable = ['event_id', 'court', 'angle', 'token_hash', 'token_hint', 'pairing_code', 'label', 'device_name', 'app_version', 'created_by'];

    protected $casts = [
        'angle' => 'integer',
        'recording' => 'boolean',
        'broadcasting' => 'boolean',
        'storage_total_bytes' => 'integer',
        'storage_free_bytes' => 'integer',
        'battery_percent' => 'integer',
        // What the console asked this camera to run at, and what the camera
        // says it is actually running. Kept apart on purpose — see MatCameras.
        'settings' => 'array',
        'reported_settings' => 'array',
        'last_seen_at' => 'datetime',
        'claimed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Never in a response, never in a log line. */
    protected $hidden = ['token_hash', 'token_hint'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function clips(): HasMany
    {
        return $this->hasMany(EventCameraClip::class, 'camera_id');
    }

    /**
     * A brand-new phone that does not yet know which mat it is.
     *
     * What the app does on first launch: ask for an identity, keep the token,
     * and stand there showing its pairing code until an organiser claims it.
     *
     * @return array{camera: self, token: string}
     */
    public static function begin(?string $deviceName = null, ?string $appVersion = null): array
    {
        $token = Str::random(40);

        $camera = static::create([
            'token_hash' => static::hash($token),
            'token_hint' => substr($token, 0, 6),
            'pairing_code' => static::freshPairingCode(),
            // Untrusted, and shown to organisers, so it is length-capped here
            // and escaped at every render.
            'device_name' => $deviceName ? Str::limit(trim($deviceName), 60, '') : null,
            'app_version' => $appVersion ? Str::limit(trim($appVersion), 20, '') : null,
        ]);

        return ['camera' => $camera, 'token' => $token];
    }

    /** An unambiguous 6-character code that is not already in use. */
    private static function freshPairingCode(): string
    {
        // Same alphabet as the screens': no vowels, no 0/O/1/I. It is read
        // aloud across a hall and typed by hand when a lens will not focus.
        $alphabet = 'BCDFGHJKLMNPQRSTVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('pairing_code', $code)->exists());

        return $code;
    }

    public function ensurePairable(): void
    {
        if (! $this->pairing_code) {
            $this->forceFill(['pairing_code' => static::freshPairingCode()])->save();
        }
    }

    public function isClaimed(): bool
    {
        return $this->event_id !== null && $this->court !== null;
    }

    /**
     * Put this camera on a mat, at an angle.
     *
     * The caller has already checked that the acting user may manage the event
     * and that the angle is free — this method does not know who is asking and
     * must never be reached without those checks.
     */
    public function claim(ClubEvent $event, string $court, int $angle, ?int $by = null): void
    {
        $this->forceFill([
            'event_id' => $event->id,
            'court' => $court,
            'angle' => $angle,
            'claimed_at' => now(),
            'created_by' => $this->created_by ?: $by,
            // Spent the moment it is used, so a photograph of the phone's
            // screen is worth nothing afterwards.
            'pairing_code' => null,
        ])->save();
    }

    /**
     * Send this camera back to its pairing code, keeping its identity.
     *
     * Unpair, not revoke: a revoked token resolves to nothing and the phone
     * would sit on a dead screen with no way back except reinstalling. This
     * way it polls, sees it is unclaimed, and shows a fresh code — which is
     * what an organiser moving a camera to another mat actually wants.
     */
    public function unclaim(): void
    {
        $this->forceFill([
            'event_id' => null,
            'court' => null,
            'angle' => null,
            'claimed_at' => null,
            'recording' => false,
            'recording_match_id' => null,
            'pairing_code' => static::freshPairingCode(),
        ])->save();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->saveQuietly();
    }

    /** The live camera holding this token, or null. One indexed read. */
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

    /** The unclaimed camera advertising this pairing code, or null. */
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

    /** Fast digest over 40 random characters — see CourtDisplayDevice::hash. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Note the phone is alive, without writing a row on every beat. */
    public function touchSeen(): void
    {
        if (! $this->last_seen_at || $this->last_seen_at->lt(now()->subMinute())) {
            $this->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    /** The stream this camera publishes, once it has asked to publish one. */
    public function liveStream(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LiveStream::class, 'live_stream_id');
    }

    /** Free space as a percentage, or null before the first beat. */
    public function storagePercentFree(): ?int
    {
        if (! $this->storage_total_bytes || $this->storage_free_bytes === null) {
            return null;
        }

        return (int) round($this->storage_free_bytes / $this->storage_total_bytes * 100);
    }

    /**
     * What an organiser's console may know about this camera.
     *
     * Deliberately not `toArray()` — no token hint, no creator id, no raw
     * timestamps. An organiser needs to tell one camera from another, see that
     * it is alive, and know whether it is about to run out of room.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        // What this camera's feed is doing. Intent and fact stay apart, as they
        // do for every other device in the product: `broadcasting` is the order
        // the console gave, `on_air` is what the media server actually sees. A
        // console that showed one as the other would light a red dot over a mat
        // with no picture behind it.
        $stream = $this->liveStream;

        return [
            'id' => $this->id,
            'angle' => $this->angle,
            'court' => $this->court,
            'label' => $this->label ?: $this->device_name ?: null,
            'recording' => (bool) $this->recording,
            'broadcasting' => (bool) $this->broadcasting,
            'on_air' => (bool) $stream?->isLive(),
            'viewers' => $stream?->isLive() ? (int) $stream->current_viewers : null,
            'air_seconds' => $stream?->isLive() ? $stream->duration_seconds : null,
            // Live broadcasting was removed from this server; there is nothing
            // to watch and no route to name.
            'watch_url' => null,
            // So the console can tell this camera's own feed apart from a
            // browser one filming the same mat.
            'stream_id' => $stream?->public_id,
            'battery' => $this->battery_percent,
            'storage_free_percent' => $this->storagePercentFree(),
            'storage_free_gb' => $this->storage_free_bytes ? round($this->storage_free_bytes / 1073741824, 1) : null,
            'clips' => $this->clips()->count(),
            // A camera beats every 30s while idle. Five minutes is generous
            // enough to survive a phone that slept through a lull, and tight
            // enough to catch one that walked out of the hall.
            'live' => $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5)),
            'last_seen' => $this->last_seen_at?->diffForHumans(),
        ];
    }
}
