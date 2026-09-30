<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a pair or a team — pointing at their own entry in the event.
 */
class EventSideMember extends Model
{
    public const ROLES = ['player', 'captain', 'substitute', 'coach'];

    /** The roles that take part in the contest itself. */
    public const COMPETING = ['player', 'captain'];

    protected $table = 'event_side_members';

    protected $fillable = ['side_id', 'registration_id', 'role', 'number', 'sort_order'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'sort_order' => 'integer'];
    }

    public function side(): BelongsTo
    {
        return $this->belongsTo(EventSide::class, 'side_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }
}
