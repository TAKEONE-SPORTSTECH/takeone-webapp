<?php

namespace App\Clubs\Support;

use App\Clubs\Models\Tenant;
use App\Federations\Support\ClubName;
use Illuminate\Support\Collection;

/**
 * "Is this club already on TakeOne?" — asked by every door that would
 * otherwise write a club down a second time.
 *
 * Two strengths, and the doors treat them differently:
 *
 *   sameName()  the same name once case, spacing, punctuation and Arabic
 *               letter forms are ignored ("JKS BAHRAIN" = "Jks Bahrain.").
 *               Strong enough to LINK when exactly one live club holds it.
 *   similar()   a recognisably similar name (the words that are not
 *               "club", "academy", "karate", "نادي"… agree ≥ 60%). Only ever
 *               OFFERED or flagged for a person to decide — two clubs can
 *               honestly share most of a name.
 *
 * A club's name and crest are public on its club page, so suggesting one by
 * name reveals nothing. Its contacts are never part of a match result.
 *
 * Federation approval keeps its own richer picker (FederationClubPicker,
 * email + phone + similar name); this is the name rule the event doors and
 * the duplicate watch share.
 */
class ClubMatch
{
    /** Words that say what KIND of club it is, not WHICH club. */
    private const STOP = ['club', 'clubs', 'academy', 'center', 'centre', 'sports', 'sport', 'team', 'the', 'and', 'school', 'gym',
        'fitness', 'martial', 'arts', 'karate', 'taekwondo', 'judo', 'jiu', 'jitsu', 'bjj', 'boxing', 'dojo',
        'نادي', 'اكاديميه', 'مركز', 'فريق'];

    /** A club name reduced to what decides it: "JKS - Bahrain." → "jks bahrain". */
    public static function key(string $name): string
    {
        $s = ClubName::norm($name);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * Live clubs holding this very name.
     *
     * @return Collection<int, Tenant>
     */
    public function sameName(string $name): Collection
    {
        $key = self::key($name);
        if (mb_strlen($key) < 2) {
            return collect();
        }

        return $this->candidates($key)->filter(fn (Tenant $t) => self::key((string) $t->getRawOriginal('club_name')) === $key)->values();
    }

    /** The one live club with this name, or null when there is none — or more than one. */
    public function uniqueSameName(string $name): ?Tenant
    {
        $found = $this->sameName($name);

        return $found->count() === 1 ? $found->first() : null;
    }

    /**
     * Live clubs with a similar name, closest first, excluding exact twins.
     *
     * @return Collection<int, array{tenant: Tenant, score: float}>
     */
    public function similar(string $name, int $limit = 5): Collection
    {
        $key = self::key($name);
        $core = $this->core($key);
        if ($core === '') {
            return collect();
        }

        return $this->candidates($key)
            ->map(function (Tenant $t) use ($key, $core) {
                $other = self::key((string) $t->getRawOriginal('club_name'));
                if ($other === $key) {
                    return null;
                }
                similar_text($core, $this->core($other), $pct);

                return $pct >= 60 ? ['tenant' => $t, 'score' => round($pct, 1)] : null;
            })
            ->filter()->sortByDesc('score')->take($limit)->values();
    }

    /** The words that name THIS club, kind-words dropped: "riffa karate club" → "riffa". */
    private function core(string $key): string
    {
        $words = array_values(array_filter(explode(' ', $key), fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::STOP, true)));

        return implode(' ', $words ?: explode(' ', $key));
    }

    /**
     * Clubs worth comparing: any sharing a significant word. Narrowed in SQL
     * by LIKE (the folding itself happens in PHP, which SQLite cannot do).
     *
     * @return Collection<int, Tenant>
     */
    private function candidates(string $key): Collection
    {
        $words = array_values(array_filter(explode(' ', $key), fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true)));
        $words = array_slice($words ?: [$key], 0, 4);

        return Tenant::query()
            ->where(function ($w) use ($words) {
                foreach ($words as $x) {
                    $w->orWhere('club_name', 'like', '%'.str_replace(['%', '_'], '', $x).'%');
                }
            })
            ->limit(60)
            ->get(['id', 'club_name', 'slug', 'logo', 'country', 'status']);
    }
}
