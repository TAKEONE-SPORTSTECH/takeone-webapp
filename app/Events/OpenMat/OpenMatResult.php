<?php

namespace App\Events\OpenMat;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One casual bout, filed.
 *
 * The read model behind "my record" — deliberately separate from the medals and
 * tournament placings on a member's profile, and never merged into them. A
 * training win over a clubmate is a real thing that happened and worth keeping;
 * it is not a competitive result, and a profile that showed the two together
 * would make both harder to read.
 */
class OpenMatResult extends Model
{
    protected $table = 'open_mat_results';

    protected $fillable = [
        'event_id', 'match_id', 'sport', 'court',
        'a_user_id', 'b_user_id', 'a_name', 'b_name',
        'winner', 'a_score', 'b_score', 'win_reason', 'win_note', 'fought_at',
    ];

    protected $casts = ['fought_at' => 'datetime'];

    /**
     * A member's casual record: fought, won, lost, and the last few opponents.
     *
     * Both sides of the table are searched because a member is 'a' in some
     * bouts and 'b' in others; the indexes are built for exactly this pair of
     * lookups. Bounded by `$limit` — a record is a summary, not an archive.
     */
    public static function recordFor(int $userId, int $limit = 20): array
    {
        $rows = static::query()
            ->where(fn ($q) => $q->where('a_user_id', $userId)->orWhere('b_user_id', $userId))
            ->orderByDesc('fought_at')->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $won = 0;
        $lost = 0;

        foreach ($rows as $row) {
            $mine = $row->a_user_id === $userId ? 'a' : 'b';

            if ($row->winner === $mine) {
                $won++;
            } elseif ($row->winner !== null) {
                $lost++;
            }
        }

        return [
            'fought' => $rows->count(),
            'won' => $won,
            'lost' => $lost,
            'bouts' => $rows->map(function (self $row) use ($userId) {
                $mine = $row->a_user_id === $userId ? 'a' : 'b';

                return [
                    'sport' => $row->sport,
                    'opponent' => $mine === 'a' ? $row->b_name : $row->a_name,
                    'opponent_user_id' => $mine === 'a' ? $row->b_user_id : $row->a_user_id,
                    'my_score' => $mine === 'a' ? $row->a_score : $row->b_score,
                    'their_score' => $mine === 'a' ? $row->b_score : $row->a_score,
                    'outcome' => $row->winner === null ? 'none' : ($row->winner === $mine ? 'won' : 'lost'),
                    'reason' => $row->win_reason,
                    'at' => $row->fought_at?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    /**
     * Just the tally — fought / won / lost — in one aggregate query.
     *
     * Separate from recordFor() because the console draws this beside every
     * corner it fills, and pulling twenty rows per corner to count three
     * numbers would put a handful of queries behind opening a sheet.
     */
    public static function summaryFor(int $userId): array
    {
        $row = static::query()
            ->selectRaw('count(*) as fought')
            ->selectRaw("sum(case when (a_user_id = ? and winner = 'a') or (b_user_id = ? and winner = 'b') then 1 else 0 end) as won", [$userId, $userId])
            ->selectRaw("sum(case when winner is not null and ((a_user_id = ? and winner = 'b') or (b_user_id = ? and winner = 'a')) then 1 else 0 end) as lost", [$userId, $userId])
            ->where(fn ($q) => $q->where('a_user_id', $userId)->orWhere('b_user_id', $userId))
            ->first();

        return [
            'fought' => (int) ($row->fought ?? 0),
            'won' => (int) ($row->won ?? 0),
            'lost' => (int) ($row->lost ?? 0),
        ];
    }

    public function aUser()
    {
        return $this->belongsTo(User::class, 'a_user_id');
    }

    public function bUser()
    {
        return $this->belongsTo(User::class, 'b_user_id');
    }
}
