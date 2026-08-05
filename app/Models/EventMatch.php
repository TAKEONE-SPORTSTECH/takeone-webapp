<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatch extends Model
{
    protected $fillable = [
        'event_id', 'category_id', 'round', 'phase', 'match_no', 'slot',
        'a_name', 'a_competitor_id', 'a_country', 'a_seed', 'a_score', 'a_provisional',
        'b_name', 'b_competitor_id', 'b_country', 'b_seed', 'b_score', 'b_provisional',
        'winner', 'court', 'scheduled_time', 'status',
    ];

    protected $casts = [
        'a_provisional' => 'boolean',
        'b_provisional' => 'boolean',
    ];

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
