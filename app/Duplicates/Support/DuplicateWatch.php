<?php

namespace App\Duplicates\Support;

use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubMatch;
use App\Duplicates\Models\DuplicateFinding;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Events\Support\KnownPerson;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use App\Members\Support\AccountMerger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform-wide look for anything that exists twice.
 *
 * Four kinds, each with its own idea of CERTAIN (what the watch may fix by
 * itself) and LIKELY (what a person decides):
 *
 *   person      two live accounts that look like one human — the members
 *               Scan's own rules (AccountMerger::scan: same email, name,
 *               phone, birthday + first name) plus the same national ID.
 *               Certain = same national ID, or the same phone AND a name
 *               KnownPerson calls the same person — and birthdates/genders
 *               that do not disagree.
 *   event_club  a club typed in for an event that is a platform club.
 *               Certain = the federation application of that name became
 *               that club. Likely = exactly one platform club has the name.
 *   club        two live platform clubs with the same name. Never certain:
 *               only an owner knows.
 *   entry       a person's spare entry row — no division, while they hold a
 *               placed entry in the same event. Certain (SpareEntries decides
 *               what may fold).
 *
 * record() is read-only towards everything except `duplicate_findings`: one
 * row per signature, refreshed every run, marked `gone` when it stops being
 * true, and a dismissed one stays dismissed.
 */
class DuplicateWatch
{
    /** Pairs per kind considered in one run — enough to stay ahead, small enough to stay cheap. */
    private const LIMIT = 500;

    public function __construct(
        private AccountMerger $merger,
        private KnownPerson $people,
        private ClubMatch $clubs,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('duplicates.watch', false);
    }

    /**
     * Everything found right now, without writing anything.
     *
     * @return array<string, array{kind:string, signature:string, a_id:?int, b_id:?int, event_id:?int, reasons:array, strength:string}>
     */
    public function scan(): array
    {
        $found = [];
        foreach ([$this->persons(), $this->eventClubs(), $this->platformClubs(), $this->spareEntries()] as $rows) {
            foreach ($rows as $row) {
                $found[$row['signature']] = $row;
            }
        }

        return $found;
    }

    /**
     * Scan and bring `duplicate_findings` into step.
     *
     * @return array{found:int, new:int, gone:int, open:int}
     */
    public function record(): array
    {
        $found = $this->scan();
        $now = now();
        $new = 0;
        $seen = [];

        foreach ($found as $sig => $row) {
            $f = DuplicateFinding::firstOrNew(['signature' => $sig]);
            if (! $f->exists) {
                $new++;
                $f->first_seen_at = $now;
                $f->status = DuplicateFinding::OPEN;
            } elseif (in_array($f->status, [DuplicateFinding::GONE, DuplicateFinding::FIXED], true)) {
                // True again after it was fixed or went away: back on the list.
                $f->status = DuplicateFinding::OPEN;
                $f->resolved_at = null;
            }
            $f->fill([
                'kind' => $row['kind'], 'a_id' => $row['a_id'], 'b_id' => $row['b_id'], 'event_id' => $row['event_id'],
                'reasons' => $row['reasons'], 'strength' => $row['strength'], 'last_seen_at' => $now,
            ])->save();
            $seen[$f->id] = true;
        }

        // What was open and is no longer found has stopped being true.
        $gone = 0;
        DuplicateFinding::where('status', DuplicateFinding::OPEN)->pluck('id')
            ->reject(fn ($id) => isset($seen[$id]))
            ->chunk(500)
            ->each(function ($ids) use (&$gone, $now) {
                $gone += DuplicateFinding::whereIn('id', $ids->all())
                    ->update(['status' => DuplicateFinding::GONE, 'resolved_at' => $now, 'updated_at' => $now]);
            });

        return [
            'found' => count($found), 'new' => $new, 'gone' => $gone,
            'open' => DuplicateFinding::where('status', DuplicateFinding::OPEN)->count(),
        ];
    }

    // ── people ──────────────────────────────────────────────────────────────

    /** @return array<int, array> */
    private function persons(): array
    {
        $pairs = [];
        foreach ($this->merger->scan(self::LIMIT) as $p) {
            $pairs[$p['a'].'-'.$p['b']] = ['a' => $p['a'], 'b' => $p['b'], 'reasons' => $p['reasons']];
        }

        // The same national ID on two people is the strongest sign there is.
        if (Schema::hasTable('federation_athletes')) {
            $twins = DB::table('federation_athletes')->whereNotNull('national_id_hash')->whereNull('anonymised_at')
                ->select('national_id_hash')->groupBy('national_id_hash')
                ->havingRaw('count(distinct user_id) > 1')->limit(self::LIMIT)->pluck('national_id_hash');
            foreach ($twins as $hash) {
                $ids = DB::table('federation_athletes')->where('national_id_hash', $hash)->distinct()->orderBy('user_id')->pluck('user_id')->all();
                for ($i = 0; $i < count($ids); $i++) {
                    for ($j = $i + 1; $j < count($ids); $j++) {
                        $k = $ids[$i].'-'.$ids[$j];
                        $pairs[$k] ??= ['a' => $ids[$i], 'b' => $ids[$j], 'reasons' => []];
                        $pairs[$k]['reasons'][] = 'Same national ID';
                    }
                }
            }
        }
        // Spellings the Scan's own name key keeps apart but are one name:
        // "Al Juma" / "Aljuma", "Abu Abdallah" / "Abuabdallah" (KnownPerson's
        // rule, which joins al/abu/bin to what follows and folds Arabic).
        $byName = [];
        foreach (User::query()->select(['id', 'full_name', 'name'])->orderBy('id')->cursor() as $u) {
            $raw = (string) ($u->getRawOriginal('full_name') ?: $u->getRawOriginal('name'));
            $key = $this->people->normalised($raw);
            // "Nil", "None", "-" typed for an empty slot is nobody, not a person twice.
            if (mb_strlen($key) >= 3 && ! \App\Federations\Support\NobodyName::is($raw)) {
                $byName[$key][] = (int) $u->id;
            }
        }
        $dismissed = Schema::hasTable('member_duplicate_dismissals')
            ? DB::table('member_duplicate_dismissals')->get(['user_low_id', 'user_high_id'])
                ->mapWithKeys(fn ($d) => [$d->user_low_id.'-'.$d->user_high_id => true])->all()
            : [];
        foreach ($byName as $ids) {
            // A name a dozen people share is a common name, not one person.
            if (count($ids) < 2 || count($ids) > 6) {
                continue;
            }
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $k = $ids[$i].'-'.$ids[$j];
                    if (! isset($dismissed[$k])) {
                        $pairs[$k] ??= ['a' => $ids[$i], 'b' => $ids[$j], 'reasons' => []];
                        $pairs[$k]['reasons'][] = 'Same name';
                    }
                }
            }
        }
        if ($pairs === []) {
            return [];
        }

        $users = User::whereIn('id', collect($pairs)->flatMap(fn ($p) => [$p['a'], $p['b']])->unique())
            ->get(['id', 'full_name', 'name', 'birthdate', 'gender', 'phone_key'])->keyBy('id');

        $out = [];
        foreach ($pairs as $p) {
            $a = $users[$p['a']] ?? null;
            $b = $users[$p['b']] ?? null;
            if (! $a || ! $b) {
                continue;   // one of them has since gone (merged, deleted)
            }
            $reasons = array_values(array_unique($p['reasons']));
            $sameName = $this->people->samePerson((string) ($a->full_name ?: $a->name), (string) ($b->full_name ?: $b->name));
            $disagree = ($a->birthdate && $b->birthdate && $a->birthdate->toDateString() !== $b->birthdate->toDateString())
                || ($a->gender && $b->gender && $a->gender !== $b->gender);
            if ($disagree) {
                $reasons[] = 'Details disagree';
            }
            $certain = ! $disagree && (
                in_array('Same national ID', $reasons, true)
                || in_array('Same email', $reasons, true)
                || (in_array('Same phone', $reasons, true) && $sameName)
            );
            [$lo, $hi] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];
            $out[] = [
                'kind' => DuplicateFinding::KIND_PERSON, 'signature' => 'person:'.$lo.'-'.$hi,
                'a_id' => $lo, 'b_id' => $hi, 'event_id' => null,
                'reasons' => $reasons, 'strength' => $certain ? 'certain' : 'likely',
            ];
        }

        return $out;
    }

    // ── clubs typed in for an event ─────────────────────────────────────────

    /** @return array<int, array> */
    private function eventClubs(): array
    {
        $typed = EventClub::whereNull('tenant_id')->whereNull('promoted_tenant_id')
            ->orderBy('id')->limit(self::LIMIT)->get(['id', 'uuid', 'event_id', 'name']);
        if ($typed->isEmpty()) {
            return [];
        }

        // The federation applications each event's clubs came from, by name.
        $applied = [];
        $fedOf = collect();
        if (Schema::hasTable('federation_events')) {
            // A club may apply to the parent body (a sports council) while the
            // event belongs to one of its federations: the whole family counts.
            $root = $this->federationRoots();
            $fedOf = FederationEvent::whereIn('club_event_id', $typed->pluck('event_id')->unique())
                ->pluck('federation_id', 'club_event_id')->map(fn ($id) => $root[$id] ?? $id);
            $apps = FederationClubApplication::where('status', FederationClubApplication::STATUS_APPROVED)
                ->whereNotNull('tenant_id')->get(['federation_id', 'name', 'tenant_id']);
            foreach ($apps as $app) {
                $applied[$root[$app->federation_id] ?? $app->federation_id][ClubMatch::key((string) $app->name)] = (int) $app->tenant_id;
            }
        }
        $live = Tenant::pluck('id')->flip();

        $out = [];
        foreach ($typed as $ec) {
            $key = ClubMatch::key((string) $ec->name);
            $fed = $fedOf[$ec->event_id] ?? null;
            $fromApp = $fed !== null ? ($applied[$fed][$key] ?? null) : null;

            if ($fromApp && isset($live[$fromApp])) {
                $out[] = $this->eventClubRow($ec, $fromApp, ['Approved federation application of this name'], 'certain');

                continue;
            }
            $same = $this->clubs->sameName((string) $ec->name);
            if ($same->count() === 1) {
                $out[] = $this->eventClubRow($ec, (int) $same->first()->id, ['Same name as a platform club'], 'likely');
            } elseif ($same->count() > 1) {
                $out[] = $this->eventClubRow($ec, null, ['Same name as '.$same->count().' platform clubs'], 'likely');
            }
        }

        return $out;
    }

    /** @return array<int, int> federation id => the id at the top of its family */
    private function federationRoots(): array
    {
        $parent = DB::table('federations')->pluck('parent_id', 'id')->all();
        $root = [];
        foreach (array_keys($parent) as $id) {
            $at = $id;
            for ($hops = 0; ! empty($parent[$at]) && $hops < 10; $hops++) {
                $at = (int) $parent[$at];
            }
            $root[$id] = $at;
        }

        return $root;
    }

    private function eventClubRow(EventClub $ec, ?int $tenantId, array $reasons, string $strength): array
    {
        return [
            'kind' => DuplicateFinding::KIND_EVENT_CLUB, 'signature' => 'event_club:'.$ec->id,
            'a_id' => (int) $ec->id, 'b_id' => $tenantId, 'event_id' => (int) $ec->event_id,
            'reasons' => $reasons, 'strength' => $strength,
        ];
    }

    // ── platform clubs ──────────────────────────────────────────────────────

    /** @return array<int, array> */
    private function platformClubs(): array
    {
        $groups = [];
        foreach (Tenant::orderBy('id')->get(['id', 'club_name']) as $t) {
            $key = ClubMatch::key((string) $t->getRawOriginal('club_name'));
            if (mb_strlen($key) >= 2) {
                $groups[$key][] = (int) $t->id;
            }
        }

        $out = [];
        foreach ($groups as $ids) {
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $out[] = [
                        'kind' => DuplicateFinding::KIND_CLUB, 'signature' => 'club:'.$ids[$i].'-'.$ids[$j],
                        'a_id' => $ids[$i], 'b_id' => $ids[$j], 'event_id' => null,
                        'reasons' => ['Same name'], 'strength' => 'likely',
                    ];
                }
            }
        }

        return array_slice($out, 0, self::LIMIT);
    }

    // ── spare entry rows ────────────────────────────────────────────────────

    /** @return array<int, array> */
    private function spareEntries(): array
    {
        $rows = ClubEventRegistration::query()
            ->where('role', 'participant')->whereNotNull('user_id')
            ->select('event_id', 'user_id')
            ->groupBy('event_id', 'user_id')
            ->havingRaw('sum(case when category_id is null then 1 else 0 end) > 0')
            ->havingRaw('sum(case when category_id is not null then 1 else 0 end) > 0')
            ->limit(self::LIMIT)->get();

        // An event entered in separate parts (Kata · Kumite) holds one row per
        // part on purpose; a row there is a second activity, not a spare.
        $parts = \App\Events\Models\ClubEvent::whereIn('id', $rows->pluck('event_id')->unique())->get()
            ->filter(fn ($ev) => \App\Events\Support\EventStreams::many($ev))->pluck('id')->flip();
        $rows = $rows->reject(fn ($r) => isset($parts[$r->event_id]));

        return $rows->map(fn ($r) => [
            'kind' => DuplicateFinding::KIND_ENTRY, 'signature' => 'entry:'.$r->event_id.'-'.$r->user_id,
            'a_id' => (int) $r->user_id, 'b_id' => null, 'event_id' => (int) $r->event_id,
            'reasons' => ['Entry with no division beside a placed one'], 'strength' => 'certain',
        ])->all();
    }
}
