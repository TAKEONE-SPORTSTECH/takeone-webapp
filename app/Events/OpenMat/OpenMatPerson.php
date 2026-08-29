<?php

namespace App\Events\OpenMat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on the floor of an open mat.
 *
 * The floor is the answer to the operational problem that made the first cut
 * of this feature unusable: with only two corners and no memory, every new pair
 * meant leaving the scoreboard to go and find both people again. People
 * accumulate here instead, so the next pair is two taps from a list that is
 * already on the scoring table.
 *
 * Three kinds of person share the row and nothing downstream distinguishes
 * them: a member the operator searched for, a member who scanned the mat's
 * code, and somebody with no account whose name was typed in.
 */
class OpenMatPerson extends Model
{
    public const SOURCE_PICKED = 'picked';

    public const SOURCE_JOINED = 'joined';

    public const SOURCE_GUEST = 'guest';

    protected $table = 'open_mat_people';

    protected $fillable = [
        'event_id', 'user_id', 'name', 'country', 'club', 'source', 'added_by', 'bouts', 'last_bout_at',
    ];

    protected $casts = ['last_bout_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The person as the floor draws them.
     *
     * A member's photo obeys `profile_picture_is_public` exactly as it does
     * everywhere else — the next thing that happens to this picture is a wall
     * screen, and a wall screen is a publication.
     */
    public function present(?string $corner = null): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'club' => $this->club,
            'source' => $this->source,
            'user_id' => $this->user_id,
            'member' => $this->user_id !== null,
            'bouts' => (int) $this->bouts,
            'photo' => ($user?->profile_picture && $user->profile_picture_is_public)
                ? asset('storage/'.$user->profile_picture)
                : null,
            'fallback' => \App\Support\Avatar::placeholder($user?->gender),
            // Their open-mat record, and only that — never a competitive one.
            'record' => $this->user_id ? OpenMatResult::summaryFor($this->user_id) : null,
            // Which corner of which mat they are standing in right now, if any.
            'corner' => $corner,
        ];
    }
}
