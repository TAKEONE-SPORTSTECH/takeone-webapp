<?php

namespace App\Parties;

use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shadow mode (PLAN.md Phase 4): ask App\Parties\Authority the same question
 * the old check just answered, and record any disagreement. The OLD answer is
 * always the one returned — this decides nothing.
 *
 * Off unless `parties.shadow`. Asked once per (check, user, subject) per
 * request, and it never throws: a failure here must not cost anybody a page.
 */
final class Shadow
{
    /** @var array<string, true> */
    private static array $asked = [];

    public static function compare(string $check, ?User $user, string $subject, bool $old, \Closure $new): bool
    {
        if (! $user || ! config('parties.shadow')) {
            return $old;
        }

        $key = $check.'|'.$user->id.'|'.$subject;
        if (isset(self::$asked[$key])) {
            return $old;
        }
        self::$asked[$key] = true;

        try {
            $answer = (bool) $new();
            if ($answer !== $old) {
                $now = now();
                $updated = DB::table('party_shadow_disagreements')
                    ->where(['check' => $check, 'user_id' => $user->id, 'subject' => $subject])
                    ->update(['old_answer' => $old, 'new_answer' => $answer, 'hits' => DB::raw('hits + 1'), 'last_seen_at' => $now]);
                if (! $updated) {
                    DB::table('party_shadow_disagreements')->insertOrIgnore([
                        'check' => $check, 'user_id' => $user->id, 'subject' => $subject,
                        'old_answer' => $old, 'new_answer' => $answer, 'first_seen_at' => $now, 'last_seen_at' => $now,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $old;
    }

    /** For tests: forget what this process has already asked. */
    public static function flush(): void
    {
        self::$asked = [];
    }
}
