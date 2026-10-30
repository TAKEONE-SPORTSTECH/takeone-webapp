<?php

namespace App\Events\Models;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatch extends Model
{
    protected $fillable = [
        'event_id', 'category_id', 'round', 'phase', 'match_no', 'slot',
        'a_name', 'a_competitor_id', 'a_country', 'a_seed', 'a_score', 'a_provisional',
        'b_name', 'b_competitor_id', 'b_country', 'b_seed', 'b_score', 'b_provisional',
        'winner', 'court', 'day', 'scheduled_time', 'status',
        // The generic competition engine (App\Events\Championship, 2026-09-27):
        // a contest between two pairs/teams, its set-by-set detail, its group.
        'a_side_id', 'b_side_id', 'detail', 'group_no',
        /*
         * ⚠️ `won_by_bye` MUST BE HERE.
         *
         * `DrawEngine::build()` writes each bout with `matches()->create([…])`,
         * so an attribute missing from this list is dropped in silence. The
         * column was added on 2026-09-18 with a backfill, which flagged every
         * bye then on file — and every bye drawn SINCE was written unflagged,
         * because this line was not updated with it.
         *
         * Nothing failed loudly. What failed is the distinction the flag exists
         * to carry: a walkover and a result look identical once both corners are
         * filled, and `Arrangement::reflow()` reads this flag to tell them
         * apart. Without it a bye that gains an opponent keeps its verdict, so
         * the newcomer is recorded as beaten by a bout nobody fought.
         */
        'won_by_bye',
        // A number the organiser set by hand — the scheduler numbers around it.
        'number_pinned',
    ];

    /**
     * Any change to a bout lets its division tidy itself once the request is
     * done (Tournament\DrawRepair — the obvious byes, seats and numbers),
     * whichever door made the change. Behind EVENT_DRAW_SELF_REPAIR.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $bout) => \App\Events\Support\Tournament\DrawRepair::touched($bout));
    }

    protected $casts = [
        'a_provisional' => 'boolean',
        'b_provisional' => 'boolean',
        'won_by_bye' => 'boolean',
        'number_pinned' => 'boolean',
        'day' => 'integer',
        'detail' => 'array',
        'group_no' => 'integer',
    ];

    /**
     * Bouts that were actually CONTESTED — two people met and it was decided.
     *
     * The distinction that matters everywhere something is about to be deleted:
     * a bracket is full of bouts carrying `status = done` and a winner that
     * nobody fought. A BYE is exactly that — one competitor, no opponent, the
     * engine marks it decided so the winner advances — and a ladder of byes is
     * scaffolding, not a record of anything.
     *
     * So a bout counts here only if BOTH corners were filled and it has an
     * outcome: a finished status, a recorded winner, or a score on the board.
     * Anything this scope returns is somebody's competition and must not be
     * deleted behind a confirmation.
     */
    public function scopeContested($query)
    {
        return $query
            ->whereNotNull('a_name')
            ->whereNotNull('b_name')
            ->where(function ($q) {
                $q->where('status', 'done')
                    ->orWhereNotNull('winner')
                    ->orWhere('a_score', '>', 0)
                    ->orWhere('b_score', '>', 0);
            });
    }

    /** The championship this bout belongs to. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /** The entry fighting in the red corner (null for a hand-typed name). */
    public function competitorA(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'a_competitor_id');
    }

    public function competitorB(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'b_competitor_id');
    }

    /** Registration id on the given side. */
    public function competitorId(string $side): ?int
    {
        return $this->{$side.'_competitor_id'};
    }

    /** Registration id of whoever won, or null while undecided. */
    public function winnerCompetitorId(): ?int
    {
        return $this->winner ? $this->competitorId($this->winner) : null;
    }

    /** Registration ids of both corners, for notifying the people actually in the bout. */
    public function competitorIds(): array
    {
        return array_values(array_filter([$this->a_competitor_id, $this->b_competitor_id]));
    }
}
