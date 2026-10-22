<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use Illuminate\Support\Facades\DB;

/**
 * "Which division is this person in?" — the draw menu's person search
 * (owner, 2026-10-18).
 *
 * Finds an event's ENTRANTS by name, phone, email or CPR, and answers with
 * each match's name, club and the divisions they are placed in. Nothing else:
 * the phone, email and CPR it matched on are search keys, never part of the
 * answer. Scoped to the one event — a person entered nowhere here is never
 * found, however exactly the query names them.
 *
 * CPR is read two ways, neither of which decrypts anything: the federation's
 * keyed hash of the athlete's national ID (FederationAthlete::hashNationalId,
 * when the Federations module is present), and a "CPR" document on the
 * member's own profile.
 *
 * Authorisation is the CALLER's (organiser only — DrawPersonController).
 */
class DrawPersonFinder
{
    /** The most people one answer carries. */
    public const LIMIT = 20;

    /** Entrants read per event: a hard ceiling, far above any real event. */
    private const SCAN_CAP = 5000;

    /**
     * @return array<int, array{name: string, club: ?string, divisions: array<int, int>}>
     */
    public function find(ClubEvent $event, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $needle = mb_strtolower($q);
        $digits = (string) preg_replace('/\D+/', '', $q);
        $isEmail = str_contains($q, '@');

        $rows = DB::table('club_event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')->whereNull('u.deleted_at')
            ->leftJoin('tenants as t', 't.id', '=', 'r.representing_tenant_id')
            ->where('r.event_id', $event->id)->where('r.role', 'participant')
            ->whereNotNull('r.category_id')
            ->limit(self::SCAN_CAP)
            ->get(['r.user_id', 'r.category_id', 'u.full_name', 'u.name', 'u.email', 'u.mobile', 'u.documents', 't.club_name']);

        // CPR through the federation's keyed hash — only when the query could be one.
        $byNationalId = [];
        $athlete = \App\Federations\Models\FederationAthlete::class;
        if (strlen($digits) >= 6 && class_exists($athlete) && method_exists($athlete, 'hashNationalId')) {
            $hash = $athlete::hashNationalId($digits);
            if ($hash) {
                $byNationalId = $athlete::where('national_id_hash', $hash)
                    ->whereIn('user_id', $rows->pluck('user_id')->unique())
                    ->pluck('user_id')->flip()->all();
            }
        }

        $people = [];
        foreach ($rows as $r) {
            if (! $this->matches($r, $needle, $digits, $isEmail, isset($byNationalId[$r->user_id]))) {
                continue;
            }
            $people[$r->user_id] ??= [
                'name' => (string) ($r->full_name ?: $r->name),
                'club' => $r->club_name ?: null,
                'divisions' => [],
            ];
            $people[$r->user_id]['divisions'][] = (int) $r->category_id;
        }

        return collect($people)
            ->map(fn ($p) => ['divisions' => array_values(array_unique($p['divisions']))] + $p)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->take(self::LIMIT)->values()->all();
    }

    private function matches(object $r, string $needle, string $digits, bool $isEmail, bool $nationalId): bool
    {
        if ($nationalId) {
            return true;
        }
        if ($isEmail) {
            // An address is matched whole, never by fragment.
            return $r->email !== null && mb_strtolower(trim((string) $r->email)) === $needle;
        }
        $name = mb_strtolower((string) ($r->full_name ?: $r->name));
        if ($name !== '' && str_contains($name, $needle)) {
            return true;
        }
        // Six or more digits and nothing else: a phone, or a CPR on the profile.
        if (strlen($digits) >= 6 && preg_match('/^[\d\s+()-]+$/', $needle)) {
            $m = json_decode((string) $r->mobile, true);
            $number = is_array($m) ? (string) preg_replace('/\D+/', '', (string) ($m['number'] ?? '')) : '';
            $full = is_array($m) ? preg_replace('/\D+/', '', (string) ($m['code'] ?? '')).$number : '';
            if ($number !== '' && ($digits === $number || $digits === $full)) {
                return true;
            }
            foreach ((array) json_decode((string) $r->documents, true) as $doc) {
                if (is_array($doc) && strtoupper((string) ($doc['type'] ?? '')) === 'CPR'
                    && preg_replace('/\D+/', '', (string) ($doc['number'] ?? '')) === $digits) {
                    return true;
                }
            }
        }

        return false;
    }
}
