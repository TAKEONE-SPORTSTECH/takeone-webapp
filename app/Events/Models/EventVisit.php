<?php

namespace App\Events\Models;

use App\Members\Models\User;
use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One PERSON who opened an event's public page — not one page view.
 *
 * The row is the visitor; `visits` counts how many times they came back. That
 * is the whole design decision: an organiser asking "how many people have seen
 * this" is asking about people, and a hit counter answers a different question
 * (mine answered 400 for an event forty people had opened).
 *
 * ── What is stored, and what deliberately is not ─────────────────────────────
 *
 * This records that somebody looked at a public page, which is personal data,
 * so it holds the least that answers the question:
 *
 *   user_id       when they were signed in — which is what lets an organiser
 *                 see that a visitor is one of their entrants.
 *   visitor_key   a ONE-WAY digest. Never an IP address: the raw address is
 *                 hashed with the app key and thrown away, so this table cannot
 *                 be turned back into a list of addresses, and the digest is
 *                 useless anywhere else.
 *   device        'mobile' | 'tablet' | 'desktop' — a shape, not a fingerprint.
 *   locale        the language they read it in. An organiser learning that
 *                 eleven people read their poster in Portuguese is the reason
 *                 the translation feature exists.
 *   referrer_host the HOST only, never the path — "instagram.com", not which
 *                 post. Where a share landed is the organiser's business; what
 *                 else the visitor was reading is not.
 *
 * NOT stored: the raw IP, the full user agent, the URL they came from, the
 * pages they went on to read, or anything at all about a signed-out visitor
 * beyond the four fields above.
 *
 * ── Who may read it ─────────────────────────────────────────────────────────
 *
 * Only somebody who may MANAGE the event (EventAccess::canManage). The count
 * and the detail are both organiser-only: a public page that named its own
 * visitors would be a leak, and one that let a stranger count them tells
 * competitors how an event is selling.
 */
class EventVisit extends Model
{
    protected $table = 'event_visits';

    protected $fillable = [
        'event_id', 'user_id', 'visitor_key', 'is_bot', 'bot_name',
        'device', 'locale', 'referrer_host', 'visits', 'recent_visits',
        'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'visits' => 'integer',
        'recent_visits' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Real people only — what every count an organiser is shown means. */
    public function scopePeople($query)
    {
        return $query->where('is_bot', false);
    }
}
