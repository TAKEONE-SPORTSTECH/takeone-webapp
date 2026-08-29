<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person who appears in one stored file.
 *
 * The file's OWNER (media_files.owner_type/owner_id) is what produced it — a
 * bout, a duel, a member's own upload. Its SUBJECTS are the people in it, and
 * there are usually several, which is exactly why they cannot be a folder.
 */
class MediaFileSubject extends Model
{
    public const ROLE_COMPETITOR = 'competitor';

    public const ROLE_COACH = 'coach';

    public const ROLE_OFFICIAL = 'official';

    public const ROLE_UPLOADER = 'uploader';

    /** Where the claim came from. A hand-made row must survive a re-cut draw. */
    public const SOURCE_DRAW = 'draw';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = ['media_file_id', 'user_id', 'role', 'source'];

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record that someone is in this file, without disturbing a claim a human
     * already made.
     *
     * The draw is recomputed whenever it is re-cut, so it must be able to
     * re-assert its rows freely — but it must never overwrite `manual`, which
     * is somebody's deliberate correction.
     */
    public static function remember(int $mediaFileId, int $userId, string $role, string $source = self::SOURCE_DRAW): void
    {
        $existing = static::where('media_file_id', $mediaFileId)
            ->where('user_id', $userId)
            ->where('role', $role)
            ->first();

        if ($existing !== null) {
            if ($existing->source === self::SOURCE_MANUAL && $source !== self::SOURCE_MANUAL) {
                return;
            }

            $existing->forceFill(['source' => $source])->save();

            return;
        }

        static::create([
            'media_file_id' => $mediaFileId,
            'user_id' => $userId,
            'role' => $role,
            'source' => $source,
        ]);
    }
}
