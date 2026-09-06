<?php

namespace App\Models;

use App\Events\Support\EventAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Members\Models\User;

/**
 * One mat, broadcasting.
 *
 * The state machine and the credential live here, so the controller stays a thin
 * door and the media server's callback hook has one place to ask questions of.
 */
class LiveStream extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'court', 'match_id', 'title', 'status', 'visibility', 'created_by',
    ];

    protected $casts = [
        'match_repointed' => 'boolean',
        'desired_at' => 'datetime',
        'camera_seen_at' => 'datetime',
        'publish_token_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'current_viewers' => 'integer',
        'peak_viewers' => 'integer',
    ];

    /** Never serialised: it is a credential, even hashed. */
    protected $hidden = ['publish_token_hash'];

    public const STATUS_IDLE = 'idle';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const STATUS_FAILED = 'failed';

    /** What the console wants this mat to be doing. See the arm/disarm pair. */
    public const WANT_IDLE = 'idle';

    public const WANT_LIVE = 'live';

    /**
     * How long a camera's beat counts for.
     *
     * The phone asks for its orders every few seconds; three missed beats and
     * the console stops claiming there is a camera on that mat. Short, unlike
     * STALE_AFTER_SECONDS, because this answers a question an organiser asks
     * while looking at the panel — "is there a phone standing by right now?" —
     * and a stale yes there is worse than a no.
     */
    public const CAMERA_PRESENT_SECONDS = 20;

    /**
     * How long a publish credential is good for.
     *
     * Two minutes, counted from pressing Go Live rather than from opening the
     * page — somebody opens the broadcast screen, then goes to find a tripod.
     */
    public const PUBLISH_TOKEN_TTL_SECONDS = 120;

    /**
     * How long a stream may claim to be live without any word from the media
     * server before it is presumed dead.
     *
     * Deliberately hours, not seconds. The media server tells us when a
     * publisher ARRIVES and when it GOES AWAY — including on a dropped
     * connection, after its own read timeout — so `status` is the real answer and
     * a short window here would mark a perfectly healthy broadcast off-air
     * simply because nobody joined or left for two minutes.
     *
     * What this guards is the one case the hooks cannot cover: the media server
     * itself restarting mid-broadcast, which leaves a row claiming to be live
     * with nothing ever coming to correct it. `live:reap` closes that properly by
     * asking the server what is actually publishing; this is the backstop for
     * when even that has not run.
     */
    public const STALE_AFTER_SECONDS = 6 * 3600;

    protected static function booted(): void
    {
        static::creating(function (self $stream) {
            // Unguessable, and constrained to what the media server's path regex
            // accepts. This is the only thing standing between an unlisted stream
            // and anybody who wants to find it.
            $stream->public_id ??= Str::random(24);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /* ── Relations ────────────────────────────────────────────────────── */

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'match_id');
    }

    /** The recording this broadcast left behind, once it has been ingested. */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── The media plane's name for this stream ───────────────────────── */

    /**
     * The path the media server knows this stream by.
     *
     * Prefixed so the server's config can allow exactly this shape and nothing
     * else: a publisher cannot invent a path outside `live/…`.
     */
    public function mediaPath(): string
    {
        return 'live/'.$this->public_id;
    }

    /* ── State ────────────────────────────────────────────────────────── */

    public function isLive(): bool
    {
        if ($this->status !== self::STATUS_LIVE) {
            return false;
        }

        // Believed live, and nothing has contradicted that. See the note on
        // STALE_AFTER_SECONDS for why this window is long rather than short.
        return $this->last_seen_at === null
            || $this->last_seen_at->gt(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }

    /** Who may broadcast: whoever may run the event. */
    public function canBroadcast(?User $user): bool
    {
        return $user !== null
            && $this->event !== null
            && app(EventAccess::class)->canManage($this->event, $user);
    }

    /**
     * Who may watch.
     *
     * Defers to the event, deliberately: an event already knows who can see its
     * draw, its results and its footage, and a live mat is not a different
     * question. `unlisted` is the escape hatch for a link shared beyond it —
     * still unguessable, still not listed anywhere.
     */
    public function isWatchableBy(?User $user): bool
    {
        if ($this->event === null) {
            return false;
        }

        if ($this->visibility === 'unlisted') {
            return true;
        }

        return $user !== null && app(EventAccess::class)->visible($this->event, $user);
    }

    /* ── The publish credential ───────────────────────────────────────── */

    /**
     * Mint the token the browser publishes with. Returned once, stored hashed.
     */
    public function issuePublishToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'publish_token_hash' => hash('sha256', $token),
            'publish_token_expires_at' => now()->addSeconds(self::PUBLISH_TOKEN_TTL_SECONDS),
        ])->save();

        return $token;
    }

    /** Constant-time comparison against the stored hash, and not expired. */
    public function publishTokenMatches(?string $presented): bool
    {
        if (! filled($presented) || ! filled($this->publish_token_hash)) {
            return false;
        }

        if ($this->publish_token_expires_at === null || $this->publish_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->publish_token_hash, hash('sha256', $presented));
    }

    /** Single use: burn it as soon as the publisher is through the door. */
    public function consumePublishToken(): void
    {
        $this->forceFill(['publish_token_hash' => null, 'publish_token_expires_at' => null])->save();
    }

    /**
     * Pressing Go Live on a stream that already ran restarts it.
     *
     * A failed first attempt — the phone denied camera access, the tripod fell —
     * must not burn the link an organiser has already shared.
     */
    public function reArm(): void
    {
        if (in_array($this->status, [self::STATUS_ENDED, self::STATUS_FAILED], true)) {
            $this->forceFill([
                'status' => self::STATUS_IDLE,
                'started_at' => null,
                'ended_at' => null,
                'current_viewers' => 0,
                // A restart is a new recording; the old one keeps its own row.
                'media_file_id' => null,
                'recording_error' => null,
                // …and a new session has not been repointed yet.
                'match_repointed' => false,
            ])->save();
        }
    }

    public function markLive(): void
    {
        $this->forceFill([
            'status' => self::STATUS_LIVE,
            'started_at' => $this->started_at ?? now(),
            'ended_at' => null,
            'last_seen_at' => now(),
        ])->save();
    }

    public function markEnded(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ENDED,
            'ended_at' => now(),
            'current_viewers' => 0,
        ])->save();
    }

    /* ── Remote arm ───────────────────────────────────────────────────── */

    /**
     * The console says: go on air.
     *
     * Intent only. It never sets `status` — a stream is live when the media
     * server says a publisher arrived, not when somebody pressed a button, and
     * pretending otherwise would put a red dot on a mat with no picture behind
     * it. `reArm` is called because arming a stream that already ran is the
     * ordinary case: the same mat, the next bout.
     */
    public function arm(?int $byUserId = null): void
    {
        $this->reArm();

        $this->forceFill([
            'desired_state' => self::WANT_LIVE,
            'desired_at' => now(),
            'desired_by' => $byUserId,
        ])->save();
    }

    /** The console says: come off air. The phone tears down on its next beat. */
    public function disarm(?int $byUserId = null): void
    {
        $this->forceFill([
            'desired_state' => self::WANT_IDLE,
            'desired_at' => now(),
            'desired_by' => $byUserId,
        ])->save();
    }

    public function isArmed(): bool
    {
        return $this->desired_state === self::WANT_LIVE;
    }

    /** Is a phone actually standing by on this stream right now? */
    public function cameraPresent(): bool
    {
        return $this->camera_seen_at !== null
            && $this->camera_seen_at->gt(now()->subSeconds(self::CAMERA_PRESENT_SECONDS));
    }

    /** The viewfinder checked in. Quiet: this happens every few seconds. */
    public function touchCamera(): void
    {
        $this->forceFill(['camera_seen_at' => now()])->saveQuietly();
    }

    public function touchSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function viewerJoined(): void
    {
        $this->forceFill([
            'current_viewers' => $this->current_viewers + 1,
            'peak_viewers' => max($this->peak_viewers, $this->current_viewers + 1),
            'last_seen_at' => now(),
        ])->save();
    }

    public function viewerLeft(): void
    {
        $this->forceFill(['current_viewers' => max(0, $this->current_viewers - 1)])->save();
    }

    /* ── Scopes and display ──────────────────────────────────────────── */

    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE)
            ->where(function ($q) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '>', now()->subSeconds(self::STALE_AFTER_SECONDS));
            });
    }

    public function getLabelAttribute(): string
    {
        return $this->title
            ?: trim(($this->court ? 'Mat '.$this->court : 'Live').($this->match_id ? ' · Bout '.$this->match_id : ''));
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        return (int) ($this->started_at->diffInSeconds($this->ended_at ?? now()));
    }
}
