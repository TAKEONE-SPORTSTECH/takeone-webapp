<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationString;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's strings table, kept per federation.
 *
 * The page holds the table as one object — `{english: {ar, fr, updated}}` —
 * seeded from translations.seed.js and saved whole. Here the seed stays the
 * seed, and only what a federation changed is stored, as rows laid over it.
 *
 * The table belongs to the TOP of the viewer's tree (the body the dashboard
 * runs), and only a manager may change it: it is every staff member's
 * interface. Values reach the screen as text nodes and three plain
 * attributes (i18n-dom.js), never as markup; they are still capped and
 * stripped of control characters here.
 */
class FederationStrings
{
    public const MAX_ROWS = 5000;
    private const MAX_KEY = 300;
    private const MAX_VALUE = 2000;

    public function __construct(private FederationApp $app) {}

    /** translations.seed.js as data: english => [ar, fr]. */
    private function seed(): array
    {
        static $seed = null;
        if ($seed === null) {
            $src = (string) @file_get_contents($this->app->root().'/translations.seed.js');
            $i = strpos($src, '{');
            $j = strrpos($src, '}');
            $seed = ($i !== false && $j !== false) ? (json_decode(substr($src, $i, $j - $i + 1), true) ?: []) : [];
        }

        return $seed;
    }

    /** `takeone_strings` for this federation: the seed with its own rows laid over it. */
    public function table(array $access): object
    {
        $out = [];
        foreach ($this->seed() as $k => $v) {
            $out[$k] = ['ar' => (string) ($v[0] ?? ''), 'fr' => (string) ($v[1] ?? ''), 'updated' => 0];
        }
        FederationString::where('federation_id', $access['root']->id)->get()->each(function (FederationString $r) use (&$out) {
            $out[$r->key] = ['ar' => (string) $r->ar, 'fr' => (string) $r->fr, 'updated' => $r->updated_at?->getTimestampMs() ?? 0];
        });

        return (object) $out;
    }

    /**
     * Save the page's whole table: store what differs from what the
     * federation has now. Answers with the table as kept.
     */
    public function sync(User $user, array $access, array $incoming): object
    {
        if (! $access['manager']) {
            return $this->table($access);
        }

        $current = (array) $this->table($access);
        $fedId = $access['root']->id;

        DB::transaction(function () use ($incoming, $current, $fedId, $user) {
            $count = FederationString::where('federation_id', $fedId)->count();
            foreach ($incoming as $key => $row) {
                if (! is_string($key) || ! is_array($row)) {
                    continue;
                }
                $key = $this->clean($key, self::MAX_KEY);
                if ($key === '') {
                    continue;
                }
                $was = $current[$key] ?? ['ar' => '', 'fr' => ''];
                $ar = array_key_exists('ar', $row) ? $this->clean((string) $row['ar'], self::MAX_VALUE) : $was['ar'];
                $fr = array_key_exists('fr', $row) ? $this->clean((string) $row['fr'], self::MAX_VALUE) : $was['fr'];
                if ($ar === $was['ar'] && $fr === $was['fr']) {
                    continue;
                }
                $existing = FederationString::where('federation_id', $fedId)->where('key', $key)->first();
                if (! $existing && $count >= self::MAX_ROWS) {
                    continue;
                }
                FederationString::updateOrCreate(
                    ['federation_id' => $fedId, 'key' => $key],
                    ['ar' => $ar, 'fr' => $fr, 'updated_by' => $user->id],
                );
                $count += $existing ? 0 : 1;
            }
        });

        return $this->table($access);
    }

    private function clean(string $s, int $max): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';

        return mb_substr(trim($s), 0, $max);
    }
}
