<?php

namespace App\Members\Support;

use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One person, one account: fold a DUPLICATE account into the one being KEPT.
 *
 * Every row anywhere on the platform that names the duplicate is re-pointed at
 * the kept account — found from the schema itself (every foreign key into
 * `users`), plus the handful of columns that reference a user without one and
 * the polymorphic ones (the audit log, media, notifications). So a table added
 * next month is covered the day it ships, as long as it declares its foreign key.
 *
 * Where re-pointing would record the same fact twice — both accounts in one
 * conversation, both members of one club, a follow between the two — the
 * duplicate's row is REMOVED, and a full copy of it goes into the ledger.
 * Anything hanging off a removed row is moved to its twin first, so nothing is
 * lost to a cascade.
 *
 * The two profiles are COMBINED (combine()): whatever one account has and the
 * other lacks is carried over, lists are joined, and where both hold a different
 * value the staying account's is used unless the admin picks the other. The
 * duplicate is soft-deleted with a direct write — NOT through
 * User::delete(), whose cleanup hook would wipe records.
 *
 * Every change is written to `account_merges`, which is what undo() reads.
 * Super-admin only; the caller authorises. Nothing here decides who may merge.
 */
class AccountMerger
{
    /** Columns naming a user with no declared foreign key. */
    private const PLAIN = [
        ['club_affiliations', 'verified_by_user_id'], ['user_notifications', 'actor_user_id'],
        ['club_events', 'started_by'], ['member_work_history', 'verified_by_user_id'],
        ['event_expenses', 'created_by'], ['event_checklist_items', 'checked_by'],
        ['club_event_registrations', 'weighed_in_by'], ['live_streams', 'desired_by'],
        ['event_public_entries', 'user_id'], ['event_public_entries', 'decided_by'],
        ['event_withdrawal_requests', 'decided_by'], ['event_clubs', 'invited_user_id'],
        ['event_clubs', 'created_by'], ['event_club_entrants', 'added_by'], ['event_visits', 'user_id'],
    ];

    /** [table, type column, id column] */
    private const MORPH = [
        ['activity_log', 'causer_type', 'causer_id'], ['activity_log', 'subject_type', 'subject_id'],
        ['media_files', 'owner_type', 'owner_id'], ['user_notifications', 'subject_type', 'subject_id'],
    ];

    /** A row that becomes somebody-with-themselves is meaningless and goes. */
    private const PAIRS = [
        'user_follows' => ['follower_id', 'followee_id'], 'user_blocks' => ['blocker_id', 'blocked_id'],
        'user_connections' => ['requester_id', 'addressee_id'], 'user_relationships' => ['guardian_user_id', 'dependent_user_id'],
        'member_duplicate_dismissals' => ['user_low_id', 'user_high_id'],
    ];

    /** One row per (this column) for a user, even without a unique index. */
    private const ONE_PER = ['memberships' => ['tenant_id'], 'club_affiliations' => ['tenant_id']];

    /**
     * The profile, COMBINED: a field one account has and the other lacks is
     * carried onto the account that stays. Only where BOTH hold a value and
     * the values differ is there anything to decide — those are the
     * `conflict` rows, the staying account's value by default, the other's
     * when the admin taps it (`$take`).
     */
    public const FIELDS = [
        'full_name' => 'Name', 'gender' => 'Gender', 'birthdate' => 'Date of birth', 'nationality' => 'Nationality',
        'mobile' => 'Mobile', 'blood_type' => 'Blood type', 'marital_status' => 'Marital status', 'height_cm' => 'Height',
        'profile_picture' => 'Photo', 'motto' => 'Motto', 'bio' => 'Bio', 'experience_years' => 'Years of experience',
    ];

    /** Lists: both accounts' entries, the same entry once. */
    public const LISTS = [
        'emergency_contacts' => 'Emergency contacts', 'health_conditions' => 'Health conditions', 'documents' => 'Documents',
        'addresses' => 'Addresses', 'social_links' => 'Social links', 'skills' => 'Skills', 'media_gallery' => 'Gallery',
    ];

    /** Placeholders that mean "not known". */
    private const BLANK = ['unknown', 'n/a', 'na', '-', '—', 'none', 'null'];

    /** More accounts than this sharing one value is a placeholder, not a person. */
    private const MAX_GROUP = 6;

    private array $moved = [];

    private array $removed = [];

    private ?array $fks = null;

    private array $indexes = [];

    /** Other accounts that look like the same person, strongest reason first. */
    public function candidates(User $user, int $limit = 8): Collection
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($user->full_name ?: $user->name))));
        $found = collect();
        $add = function ($q, string $why) use (&$found, $user) {
            foreach ($q->where('id', '!=', $user->id)->limit(20)->get() as $u) {
                $row = $found->get($u->id) ?? ['user' => $u, 'reasons' => []];
                $row['reasons'][] = $why;
                $found->put($u->id, $row);
            }
        };
        if ($user->phone_key) {
            $add(User::where('phone_key', $user->phone_key), 'Same phone');
        }
        if ($user->email) {
            $add(User::whereRaw('lower(email) = ?', [mb_strtolower($user->email)]), 'Same email');
        }
        if ($name !== '') {
            $add(User::whereRaw('lower(trim(full_name)) = ?', [$name]), 'Same name');
        }
        if ($user->birthdate && $name !== '') {
            $first = explode(' ', $name)[0];
            $add(User::whereDate('birthdate', $user->birthdate)->whereRaw('lower(full_name) like ?', [$first.'%']), 'Same birthday');
        }

        return $found->sortByDesc(fn ($r) => count($r['reasons']))->take($limit)->values();
    }

    /**
     * Every pair of live accounts that look like one person, strongest first.
     *
     * Same email, same name (case, spacing and punctuation ignored), same
     * phone, same birthday with the same first name. A value shared by more
     * than MAX_GROUP accounts is a placeholder, not a person, and is skipped.
     * Pairs a super-admin marked "not the same person" are left out.
     *
     * @return array<int, array{a:int, b:int, reasons:string[], score:int}>
     */
    public function scan(int $limit = 100): array
    {
        $weights = ['e' => ['Same email', 3], 'n' => ['Same name', 3], 'p' => ['Same phone', 2], 'b' => ['Same birthday & first name', 2]];
        $groups = [];
        foreach (User::query()->select(['id', 'full_name', 'name', 'email', 'phone_key', 'birthdate'])->orderBy('id')->cursor() as $u) {
            $name = $this->nameKey((string) ($u->getRawOriginal('full_name') ?: $u->getRawOriginal('name')));
            $email = mb_strtolower(trim((string) $u->getRawOriginal('email')));
            $phone = (string) $u->getRawOriginal('phone_key');
            $bday = substr((string) $u->getRawOriginal('birthdate'), 0, 10);
            if ($email !== '') {
                $groups['e:'.$email][] = $u->id;
            }
            if (mb_strlen($name) >= 3 && str_contains($name, ' ')) {
                $groups['n:'.$name][] = $u->id;
            }
            if (strlen($phone) >= 6) {
                $groups['p:'.$phone][] = $u->id;
            }
            if ($bday !== '' && $name !== '') {
                $groups['b:'.$bday.'|'.explode(' ', $name)[0]][] = $u->id;
            }
        }

        $pairs = [];
        foreach ($groups as $key => $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2 || count($ids) > self::MAX_GROUP) {
                continue;
            }
            [$why, $w] = $weights[$key[0]];
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $k = $ids[$i].'-'.$ids[$j];
                    $pairs[$k] ??= ['a' => $ids[$i], 'b' => $ids[$j], 'reasons' => [], 'score' => 0];
                    if (! in_array($why, $pairs[$k]['reasons'], true)) {
                        $pairs[$k]['reasons'][] = $why;
                        $pairs[$k]['score'] += $w;
                    }
                }
            }
        }
        if ($pairs && Schema::hasTable('member_duplicate_dismissals')) {
            foreach (DB::table('member_duplicate_dismissals')->get(['user_low_id', 'user_high_id']) as $d) {
                unset($pairs[$d->user_low_id.'-'.$d->user_high_id]);
            }
        }
        usort($pairs, fn ($x, $y) => [$y['score'], $x['a']] <=> [$x['score'], $y['a']]);

        return array_slice($pairs, 0, $limit);
    }

    /** "Jawad  Abdulhadi." and "jawad abdulhadi" are one name. */
    private function nameKey(string $name): string
    {
        $name = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name));

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /** Mark a flagged pair as two different people. */
    public function dismiss(User $a, User $b, User $by): void
    {
        [$lo, $hi] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];
        DB::table('member_duplicate_dismissals')->updateOrInsert(
            ['user_low_id' => $lo, 'user_high_id' => $hi],
            ['dismissed_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Any live account by name, email, phone or TakeOne ID — INCLUDING the ones
     * with no email, which MemberLookup (a sign-in-shaped search) leaves out and
     * which are exactly what duplicates usually are.
     */
    public function search(string $typed, User $except, int $limit = 10): Collection
    {
        $q = trim(str_replace(['%', '_'], '', $typed));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            return collect();
        }
        $query = User::query()->where('id', '!=', $except->id);
        if (preg_match('/^[A-Za-z]{3}-\d{1,6}$/', $q) && Schema::hasTable('federation_athletes')) {
            $query->whereIn('id', DB::table('federation_athletes')->where('takeone_id', 'like', strtoupper($q).'%')->limit($limit)->pluck('user_id'));
        } elseif (preg_match('/^[\d\s+\-().]+$/', $q) && strlen(preg_replace('/\D/', '', $q)) >= 4) {
            $query->withPhone($q);
        } else {
            $like = '%'.$q.'%';
            $query->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', mb_strtolower($like)));
        }

        return $query->orderBy('full_name')->limit($limit)->get();
    }

    /**
     * What merging $dup into $keep would do, or why it may not.
     *
     * @return array{blockers:string[], warnings:string[], counts:array<string,int>, total:int}
     */
    public function plan(User $dup, User $keep): array
    {
        $blockers = [];
        if ($dup->id === $keep->id) {
            $blockers[] = 'That is the same account.';
        }
        if ($dup->trashed() || $keep->trashed()) {
            $blockers[] = 'One of these accounts has already been removed.';
        }
        if ($dup->isSuperAdmin()) {
            $blockers[] = 'A platform administrator account cannot be merged away.';
        }
        if (Schema::hasTable('federation_athletes')
            && DB::table('federation_athletes')->where('user_id', $dup->id)->exists()
            && DB::table('federation_athletes')->where('user_id', $keep->id)->exists()) {
            $blockers[] = 'Both accounts hold a TakeOne ID. Merge the two athletes on the federation\'s Athletes page first.';
        }
        if (Schema::hasTable('persons')
            && DB::table('persons')->where('user_id', $dup->id)->whereNull('deleted_at')->exists()
            && DB::table('persons')->where('user_id', $keep->id)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'Both accounts are placed in a family tree. Merge the two people in the family tree first.';
        }

        $warnings = [];
        if ($this->signsIn($dup) && $this->signsIn($keep)) {
            $warnings[] = 'Both accounts have been used to sign in. After the merge they sign in with '.$keep->getRawOriginal('email').' only.';
        }

        $counts = [];
        foreach ($this->references() as [$table, $col]) {
            $n = DB::table($table)->where($col, $dup->id)->count();
            if ($n) {
                $counts[$table] = ($counts[$table] ?? 0) + $n;
            }
        }
        foreach (self::MORPH as [$table, $type, $col]) {
            if (Schema::hasTable($table)) {
                $n = DB::table($table)->whereIn($type, $this->morphTypes())->where($col, $dup->id)->count();
                if ($n) {
                    $counts[$table] = ($counts[$table] ?? 0) + $n;
                }
            }
        }
        arsort($counts);

        return ['blockers' => $blockers, 'warnings' => $warnings, 'counts' => $counts, 'total' => array_sum($counts)];
    }

    /** Fold $dup into $keep. Throws \RuntimeException with a readable reason. */
    public function merge(User $dup, User $keep, User $by, array $take = []): \App\Members\Models\AccountMerge
    {
        $plan = $this->plan($dup, $keep);
        if ($plan['blockers']) {
            throw new \RuntimeException($plan['blockers'][0]);
        }
        $this->moved = [];
        $this->removed = [];

        return DB::transaction(function () use ($dup, $keep, $by, $take) {
            $before = ['keep' => [], 'dup' => ['deleted_at' => null, 'email' => $dup->getRawOriginal('email')]];

            foreach (self::ONE_PER as $table => $cols) {
                $this->foldOnePer($table, $cols, $dup->id, $keep->id);
            }
            foreach ($this->references() as [$table, $col]) {
                $this->repoint($table, $col, $dup->id, $keep->id);
            }
            foreach (self::MORPH as [$table, $type, $col]) {
                if (Schema::hasTable($table)) {
                    foreach (DB::table($table)->whereIn($type, $this->morphTypes())->where($col, $dup->id)->get() as $row) {
                        $this->set($table, (array) $row, $col, $dup->id, $keep->id);
                    }
                }
            }
            // Credentials never move: the duplicate's sign-ins and tokens simply end.
            DB::table('sessions')->where('user_id', $dup->id)->delete();
            foreach (DB::table('personal_access_tokens')->whereIn('tokenable_type', $this->morphTypes())->where('tokenable_id', $dup->id)->get() as $row) {
                $this->remove('personal_access_tokens', (array) $row);
            }

            // Soft-delete first: the email index only counts live accounts.
            DB::table('users')->where('id', $dup->id)->update(['deleted_at' => now(), 'updated_at' => now()]);

            $fill = [];
            foreach ($this->combine($dup, $keep) as $row) {
                if ($row['from'] === 'merge' || ($row['conflict'] && in_array($row['field'], $take, true))) {
                    $fill[$row['field']] = $row['kind'] === 'list' ? $row['combined'] : $dup->getRawOriginal($row['field']);
                } elseif ($row['kind'] === 'list' && $row['from'] === 'both') {
                    $fill[$row['field']] = $row['combined'];
                }
            }
            if (blank($keep->getRawOriginal('name')) && filled($dup->getRawOriginal('name'))) {
                $fill['name'] = $dup->getRawOriginal('name');
            }
            if (! $keep->getRawOriginal('is_personal_trainer') && $dup->getRawOriginal('is_personal_trainer')) {
                $fill['is_personal_trainer'] = 1;
            }
            if (blank($keep->getRawOriginal('email')) && filled($dup->getRawOriginal('email'))) {
                $fill['email'] = $dup->getRawOriginal('email');
                $fill['email_verified_at'] = $dup->getRawOriginal('email_verified_at');
            }
            if ($fill) {
                foreach (array_keys($fill) as $f) {
                    $before['keep'][$f] = $keep->getRawOriginal($f);
                }
                $before['keep']['phone_key'] = $keep->getRawOriginal('phone_key');
                // Raw column values: a list goes in as its JSON, a copied
                // value exactly as the other account stored it.
                foreach ($fill as $f => $v) {
                    if (is_array($v)) {
                        $fill[$f] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                DB::table('users')->where('id', $keep->id)->update($fill + ['updated_at' => now()]);
                $keep->refresh();
                // phone_key is derived from mobile and must follow it.
                if (array_key_exists('mobile', $fill)) {
                    DB::table('users')->where('id', $keep->id)->update(['phone_key' => User::normalisePhone($keep->mobile)]);
                }
            }

            $log = \App\Members\Models\AccountMerge::create([
                'uuid' => (string) Str::uuid(),
                'kept_user_id' => $keep->id,
                'merged_user_id' => $dup->id,
                'merged_by' => $by->id,
                'moved' => $this->moved,
                'removed' => $this->removed,
                'user_before' => $before,
            ]);
            activity()->causedBy($by)->performedOn($keep)
                ->withProperties(['merged_user' => $dup->uuid, 'merge' => $log->uuid, 'rows' => count($this->moved), 'removed' => count($this->removed)])
                ->log('platform.members_merged');

            return $log;
        });
    }

    /** Put everything back the way the ledger says it was. */
    public function undo(\App\Members\Models\AccountMerge $log): void
    {
        if ($log->undone_at) {
            throw new \RuntimeException('That merge has already been undone.');
        }
        DB::transaction(function () use ($log) {
            // The kept account gives back what it borrowed (the email) before the duplicate returns.
            $keepBefore = (array) ($log->user_before['keep'] ?? []);
            if ($keepBefore) {
                DB::table('users')->where('id', $log->kept_user_id)->update($keepBefore);
            }
            DB::table('users')->where('id', $log->merged_user_id)->update(['deleted_at' => null, 'updated_at' => now()]);
            foreach (array_reverse((array) $log->removed) as $r) {
                DB::table($r['t'])->insertOrIgnore($r['row']);
            }
            foreach (array_reverse((array) $log->moved) as $m) {
                DB::table($m['t'])->where($m['k'])->where($m['c'], $m['to'])->update([$m['c'] => $m['from']]);
            }
            $log->forceFill(['undone_at' => now()])->save();
        });
    }

    /**
     * Field by field, what the one combined account will hold.
     *
     * `from`: keep (only the staying account has it, or both agree) · merge
     * (only the other has it — it is carried over) · both (a list, entries
     * from each) · none. `conflict`: both hold a DIFFERENT value.
     *
     * @return array<int, array{field:string,label:string,kind:string,keep:mixed,merge:mixed,from:string,conflict:bool,combined?:array}>
     */
    public function combine(User $dup, User $keep): array
    {
        $rows = [];
        foreach (self::FIELDS as $f => $label) {
            $a = $this->hasValue($keep, $f);
            $b = $this->hasValue($dup, $f);
            $same = $a && $b && $this->norm($keep, $f) === $this->norm($dup, $f);
            $rows[] = [
                'field' => $f, 'label' => $label, 'kind' => $f === 'profile_picture' ? 'photo' : 'text',
                'keep' => $a ? $this->display($keep, $f) : null, 'merge' => $b ? $this->display($dup, $f) : null,
                'from' => $a ? 'keep' : ($b ? 'merge' : 'none'),
                'conflict' => $a && $b && ! $same,
            ];
        }
        foreach (self::LISTS as $f => $label) {
            $a = $this->listOf($keep, $f);
            $b = $this->listOf($dup, $f);
            $combined = $this->union($a, $b);
            $added = count($combined) - count($a);
            $rows[] = [
                'field' => $f, 'label' => $label, 'kind' => 'list',
                'keep' => $a ? count($a) : null, 'merge' => $b ? count($b) : null,
                'from' => $a && $added > 0 ? 'both' : ($a ? 'keep' : ($b ? 'merge' : 'none')),
                'conflict' => false, 'combined' => $combined, 'total' => count($combined),
            ];
        }

        return $rows;
    }

    private function listOf(User $u, string $f): array
    {
        $v = $u->getRawOriginal($f);
        $v = is_string($v) ? json_decode($v, true) : $v;

        return is_array($v) ? array_filter($v, fn ($x) => $x !== null && $x !== '' && $x !== []) : [];
    }

    /** A list: the staying account's entries, then the other's not already there. A map: the staying account's keys win. */
    private function union(array $a, array $b): array
    {
        if (! array_is_list($a) || ! array_is_list($b)) {
            return $a + $b;
        }
        $key = function ($x) {
            if (is_string($x)) {
                return mb_strtolower(trim($x));
            }
            if (is_array($x)) {
                ksort($x);
                unset($x['uploaded_at'], $x['id']);

                return json_encode(array_map(fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : $v, $x));
            }

            return json_encode($x);
        };
        $seen = [];
        $out = [];
        foreach (array_merge(array_values($a), array_values($b)) as $x) {
            $k = $key($x);
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $x;
            }
        }

        return $out;
    }

    /** What decides whether two values are the same fact. */
    private function norm(User $u, string $f): string
    {
        $v = $u->getRawOriginal($f);
        if ($f === 'mobile') {
            $m = is_string($v) ? json_decode($v, true) : $v;

            return preg_replace('/\D/', '', (string) ($m['code'] ?? '').(string) ($m['number'] ?? ''));
        }
        if ($f === 'birthdate') {
            return substr((string) $v, 0, 10);
        }

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
    }

    /** How a value reads in the preview. */
    private function display(User $u, string $f): string
    {
        $v = $u->getRawOriginal($f);

        return match ($f) {
            'mobile' => (function () use ($v) { $m = is_string($v) ? json_decode($v, true) : $v;

                return trim(($m['code'] ?? '').' '.($m['number'] ?? '')); })(),
            'birthdate' => ($d = \Carbon\Carbon::make($v)) ? $d->format('d/m/Y') : (string) $v,
            'nationality' => \App\Support\Countries::name((string) $v) ?: (string) $v,
            'height_cm' => $v.' cm',
            'profile_picture' => (string) \App\Members\Support\ProfilePhoto::url($u),
            default => mb_strimwidth((string) $v, 0, 140, '…'),
        };
    }

    /** A phone stored as {"code":"+973","number":null} is no phone. */
    private function hasValue(User $u, string $f): bool
    {
        $v = $u->getRawOriginal($f);
        if ($f === 'mobile') {
            $m = is_string($v) ? json_decode($v, true) : $v;

            return is_array($m) ? filled($m['number'] ?? null) : filled($v);
        }
        if (is_string($v) && in_array(mb_strtolower(trim($v)), self::BLANK, true)) {
            return false;
        }

        return filled($v);
    }

    public function signsIn(User $u): bool
    {
        return ! $u->is_unclaimed && filled($u->getRawOriginal('email')) && filled($u->getRawOriginal('password'));
    }

    // ── internals ────────────────────────────────────────────────────────

    /** Two rows for one club (membership, affiliation): the kept account's stays, the duplicate's folds into it. */
    private function foldOnePer(string $table, array $cols, int $dup, int $keep): void
    {
        $userCol = $table === 'club_affiliations' ? 'member_id' : 'user_id';
        foreach (DB::table($table)->where($userCol, $dup)->get() as $row) {
            $row = (array) $row;
            $q = DB::table($table)->where($userCol, $keep);
            foreach ($cols as $c) {
                if ($row[$c] === null) {
                    continue 2;
                }
                $q->where($c, $row[$c]);
            }
            $twin = $q->first();
            if (! $twin) {
                continue;
            }
            $twin = (array) $twin;
            if ($table === 'memberships' && $row['status'] === 'active' && $twin['status'] !== 'active') {
                $this->set($table, $twin, 'status', $twin['status'], 'active');
            }
            if ($table === 'club_affiliations' && $row['start_date'] && (! $twin['start_date'] || $row['start_date'] < $twin['start_date'])) {
                $this->set($table, $twin, 'start_date', $twin['start_date'], $row['start_date']);
            }
            $this->moveDependents($table, (int) $row['id'], (int) $twin['id']);
            $this->remove($table, $row);
        }
    }

    private function repoint(string $table, string $col, int $dup, int $keep): void
    {
        $uniques = collect($this->indexes($table))->filter(fn ($i) => $i['unique'] && ! $i['primary'] && in_array($col, $i['columns'], true));
        foreach (DB::table($table)->where($col, $dup)->get() as $row) {
            $row = (array) $row;
            if (isset(self::PAIRS[$table]) && in_array($col, self::PAIRS[$table], true)) {
                $other = self::PAIRS[$table][0] === $col ? self::PAIRS[$table][1] : self::PAIRS[$table][0];
                if ((int) $row[$other] === $keep) {
                    $this->remove($table, $row);

                    continue;
                }
            }
            $twin = null;
            foreach ($uniques as $ix) {
                $q = DB::table($table);
                foreach ($ix['columns'] as $c) {
                    $v = $c === $col ? $keep : $row[$c];
                    $v === null ? $q->whereNull($c) : $q->where($c, $v);
                }
                if ($twin = $q->first()) {
                    break;
                }
            }
            if ($twin) {
                if (isset($row['id'], $twin->id)) {
                    $this->moveDependents($table, (int) $row['id'], (int) $twin->id);
                }
                $this->remove($table, $row);
            } else {
                $this->set($table, $row, $col, $dup, $keep);
            }
        }
    }

    /** Rows of other tables hanging off $table#$from move to $table#$to before $from goes. */
    private function moveDependents(string $table, int $from, int $to): void
    {
        foreach ($this->foreignKeys() as [$child, $col, $parent]) {
            if ($parent !== $table) {
                continue;
            }
            foreach (DB::table($child)->where($col, $from)->get() as $row) {
                try {
                    $this->set($child, (array) $row, $col, $from, $to);
                } catch (\Illuminate\Database\QueryException $e) {
                    throw new \RuntimeException('Could not combine the two accounts\' '.str_replace('_', ' ', $child).'. Nothing was changed.');
                }
            }
        }
    }

    private function set(string $table, array $row, string $col, $from, $to): void
    {
        $key = $this->key($table, $row);
        DB::table($table)->where($key)->where($col, $from)->update([$col => $to]);
        $this->moved[] = ['t' => $table, 'k' => $key, 'c' => $col, 'from' => $from, 'to' => $to];
    }

    private function remove(string $table, array $row): void
    {
        DB::table($table)->where($this->key($table, $row))->delete();
        $this->removed[] = ['t' => $table, 'row' => $row];
    }

    private function key(string $table, array $row): array
    {
        $pk = collect($this->indexes($table))->firstWhere('primary', true)['columns'] ?? null;

        return array_intersect_key($row, array_flip($pk ?: array_keys($row)));
    }

    /** Every [table, column] naming a user id. */
    private function references(): array
    {
        $refs = [];
        foreach ($this->foreignKeys() as [$table, $col, $parent]) {
            if ($parent === 'users') {
                $refs[] = [$table, $col];
            }
        }
        foreach (self::PLAIN as [$table, $col]) {
            if (Schema::hasColumn($table, $col) && ! in_array([$table, $col], $refs, true)) {
                $refs[] = [$table, $col];
            }
        }

        return $refs;
    }

    /** [child table, column, parent table] for every single-column foreign key. */
    private function foreignKeys(): array
    {
        if ($this->fks !== null) {
            return $this->fks;
        }
        $this->fks = [];
        foreach (Schema::getTables() as $t) {
            if ($t['name'] === 'account_merges' || str_starts_with($t['name'], 'sqlite_')) {
                continue;
            }
            foreach (Schema::getForeignKeys($t['name']) as $fk) {
                if (count($fk['columns']) === 1) {
                    $this->fks[] = [$t['name'], $fk['columns'][0], $fk['foreign_table']];
                }
            }
        }

        return $this->fks;
    }

    private function indexes(string $table): array
    {
        return $this->indexes[$table] ??= Schema::getIndexes($table);
    }

    private function morphTypes(): array
    {
        return array_values(array_unique([(new User)->getMorphClass(), User::class, 'App\\Models\\User']));
    }
}
