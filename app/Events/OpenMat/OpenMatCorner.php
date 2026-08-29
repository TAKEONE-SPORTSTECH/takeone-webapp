<?php

namespace App\Events\OpenMat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One corner of one mat: who is standing there right now.
 *
 * Three kinds of person fill the same row, which is the point:
 *   · a TAKEONE member the operator searched for      (source: picked)
 *   · a member who took the corner with the mat's code (source: joined)
 *   · somebody with no account, typed in               (source: guest)
 *
 * Downstream nothing cares which — the board prints `name`, and `user_id`
 * decides only whether the bout lands on anybody's casual record.
 */
class OpenMatCorner extends Model
{
    public const SIDE_AKA = 'aka';   // red

    public const SIDE_AO = 'ao';     // blue

    public const SIDES = [self::SIDE_AKA, self::SIDE_AO];

    public const SOURCE_PICKED = 'picked';

    public const SOURCE_JOINED = 'joined';

    public const SOURCE_GUEST = 'guest';

    protected $table = 'open_mat_corners';

    protected $fillable = [
        'event_id', 'person_id', 'court', 'side', 'user_id', 'name', 'country', 'club', 'source', 'placed_by',
    ];

    /**
     * The person standing here.
     *
     * A corner is a POSITION; the person is on the floor and may move between
     * corners and mats all evening. The name/user_id/country columns beside
     * this pointer are a denormalised snapshot, exactly as `event_matches`
     * keeps `a_name` beside `a_competitor_id` — a board reads names, never a
     * join.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(OpenMatPerson::class, 'person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The corner as the console draws it.
     *
     * A member's photo obeys profile_picture_is_public exactly as it does
     * everywhere else — the next thing that happens to this picture is a wall
     * screen, and a wall screen is a publication.
     */
    public function present(): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'side' => $this->side,
            'name' => $this->name,
            'country' => $this->country,
            'club' => $this->club,
            'source' => $this->source,
            'user_id' => $this->user_id,
            'member' => $this->user_id !== null,
            'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                ? file_url($user->profile_picture)
                : null,
            'fallback' => \App\Support\Avatar::placeholder($user?->gender),
            // Their casual record — how they have done on open mats, and
            // NOTHING else. Never a competitive record: medals and placings
            // live on the profile and are a different kind of fact. A guest
            // has none, because a guest is not anybody the platform knows.
            'record' => $this->user_id ? OpenMatResult::summaryFor($this->user_id) : null,
        ];
    }
}
