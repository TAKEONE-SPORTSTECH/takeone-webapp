<?php

namespace App\Duplicates\Support;

use App\Clubs\Models\Tenant;
use App\Duplicates\Models\DuplicateFinding;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Events\Support\Clubs\EventClubLink;
use App\Events\Support\SpareEntries;
use App\Members\Models\User;
use App\Members\Support\AccountMerger;

/**
 * The watch fixing what it is CERTAIN of — and nothing else.
 *
 *   event_club  linked to the platform club its own federation application
 *               became (EventClubLink: fills empty fields only).
 *   person      merged (AccountMerger, which keeps an undo ledger) — but only
 *               when at least one side is an unclaimed record nobody signs in
 *               with. Two accounts that can both sign in are two people's
 *               logins until a super-admin says otherwise, so they are left.
 *               The kept record is the one that signs in; between two
 *               unclaimed records, the older.
 *   entry       the spare row folded (SpareEntries, which carries fees and
 *               links first) — only while events.spare_entries is on.
 *   club        never: only an owner knows whether two clubs are one.
 *
 * A finding a fix was refused for stays open, with the reason in `fix`, so
 * the next run does not hammer it and a person can see why.
 */
class DuplicateFixer
{
    public function __construct(private AccountMerger $merger) {}

    public static function enabled(): bool
    {
        return (bool) config('duplicates.autofix', false);
    }

    /** Whom a fix is recorded as: the configured super-admin, else the oldest one. */
    public function actor(): ?User
    {
        $q = User::whereHas('roles', fn ($r) => $r->where('slug', 'super-admin'));
        $email = trim((string) config('duplicates.actor_email'));

        return ($email !== '' ? (clone $q)->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null)
            ?? $q->orderBy('id')->first();
    }

    /**
     * Fix every open, certain finding the rules allow.
     *
     * @return array{fixed:int, refused:int}
     */
    public function run(): array
    {
        $by = $this->actor();
        if (! $by) {
            return ['fixed' => 0, 'refused' => 0];
        }
        $max = max(0, (int) config('duplicates.max_fixes_per_run', 25));
        $fixed = 0;
        $refused = 0;

        $open = DuplicateFinding::where('status', DuplicateFinding::OPEN)->where('strength', 'certain')
            ->where('kind', '!=', DuplicateFinding::KIND_CLUB)
            ->orderBy('id')->limit(200)->get();

        foreach ($open as $f) {
            if ($fixed >= $max) {
                break;
            }
            // A refusal is retried once a day, not every hour.
            $at = $f->fix['at'] ?? null;
            if (isset($f->fix['refused']) && $at && now()->subDay()->lt(\Illuminate\Support\Carbon::parse($at))) {
                continue;
            }
            $r = rescue(fn () => $this->fix($f, $by), ['ok' => false, 'message' => 'error'], report: true);
            $r['ok'] ? $fixed++ : $refused++;
        }

        return ['fixed' => $fixed, 'refused' => $refused];
    }

    /**
     * Fix one finding. Records the outcome on it either way.
     *
     * @return array{ok:bool, message:string}
     */
    public function fix(DuplicateFinding $f, User $by, bool $confirmedByPerson = false): array
    {
        // A person may confirm a LIKELY typed-club link (one named platform
        // club); every other likely finding is decided in its own screen —
        // a person merge needs the merge screen's field-by-field choices.
        $confirmable = $confirmedByPerson && $f->kind === DuplicateFinding::KIND_EVENT_CLUB && $f->b_id;
        if (! $f->isOpen() || (! $f->isCertain() && ! $confirmable)) {
            return ['ok' => false, 'message' => 'not_open_or_not_certain'];
        }

        $r = match ($f->kind) {
            DuplicateFinding::KIND_EVENT_CLUB => $this->eventClub($f, $by),
            DuplicateFinding::KIND_PERSON => $this->person($f, $by),
            DuplicateFinding::KIND_ENTRY => $this->entry($f),
            default => ['ok' => false, 'message' => 'never_automatic'],
        };

        if ($r['ok']) {
            $f->forceFill([
                'status' => DuplicateFinding::FIXED, 'resolved_at' => now(), 'resolved_by' => $by->id,
                'fix' => ($r['fix'] ?? []) + ['by' => $confirmedByPerson ? 'person' : 'watch', 'at' => now()->toIso8601String()],
            ])->save();
        } else {
            $f->forceFill(['fix' => ['refused' => $r['message'], 'at' => now()->toIso8601String()]])->save();
        }

        return ['ok' => $r['ok'], 'message' => $r['message']];
    }

    /**
     * "Not a duplicate." A person pair is also recorded as two different
     * people (member_duplicate_dismissals — the members Scan's own record),
     * so neither the Scan nor the watch raises it again.
     */
    public function dismiss(DuplicateFinding $f, User $by): void
    {
        if ($f->kind === DuplicateFinding::KIND_PERSON && ($a = User::find($f->a_id)) && ($b = User::find($f->b_id))) {
            $this->merger->dismiss($a, $b, $by);
        }
        $f->forceFill(['status' => DuplicateFinding::DISMISSED, 'resolved_at' => now(), 'resolved_by' => $by->id])->save();
    }

    private function eventClub(DuplicateFinding $f, User $by): array
    {
        $club = EventClub::find($f->a_id);
        $tenant = $f->b_id ? Tenant::find($f->b_id) : null;
        if (! $club || ! $tenant) {
            return ['ok' => false, 'message' => 'missing'];
        }
        $r = EventClubLink::link($club, $tenant, $by, 'duplicate_watch');

        return $r['ok']
            ? ['ok' => true, 'message' => 'linked', 'fix' => ['action' => 'linked', 'tenant_id' => $tenant->id, 'entries' => $r['entries'], 'officials' => $r['officials']]]
            : ['ok' => false, 'message' => $r['message'] ?? 'refused'];
    }

    private function person(DuplicateFinding $f, User $by): array
    {
        $a = User::find($f->a_id);
        $b = User::find($f->b_id);
        if (! $a || ! $b) {
            return ['ok' => false, 'message' => 'missing'];
        }
        $aSigns = $this->merger->signsIn($a);
        $bSigns = $this->merger->signsIn($b);
        if ($aSigns && $bSigns) {
            return ['ok' => false, 'message' => 'both_sign_in'];
        }
        // Keep the one that signs in; between two unclaimed records, the older.
        [$keep, $dup] = $aSigns ? [$a, $b] : ($bSigns ? [$b, $a] : ($a->id < $b->id ? [$a, $b] : [$b, $a]));

        $plan = $this->merger->plan($dup, $keep);
        if ($plan['blockers']) {
            return ['ok' => false, 'message' => $plan['blockers'][0]];
        }
        $log = $this->merger->merge($dup, $keep, $by);

        return ['ok' => true, 'message' => 'merged', 'fix' => ['action' => 'merged', 'kept' => $keep->id, 'merged' => $dup->id, 'merge_id' => $log->id]];
    }

    private function entry(DuplicateFinding $f): array
    {
        if (! SpareEntries::enabled()) {
            return ['ok' => false, 'message' => 'spare_entries_off'];
        }
        $event = ClubEvent::find($f->event_id);
        if (! $event) {
            return ['ok' => false, 'message' => 'missing'];
        }
        $n = SpareEntries::fold($event, [(int) $f->a_id]);

        return $n > 0
            ? ['ok' => true, 'message' => 'folded', 'fix' => ['action' => 'folded', 'rows' => $n]]
            : ['ok' => false, 'message' => 'not_foldable'];
    }
}
