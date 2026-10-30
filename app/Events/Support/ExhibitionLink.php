<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventExhibitionBout;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turn a side exhibition into the draw bout it really was (owner, 2026-10-24).
 *
 * On the day the draw could not be reached — a locked bracket, a console that
 * would not load the bout — so the mat fought it as an exhibition (EX3). This
 * answers the two questions that follow:
 *
 *  · WHICH draw bout was it? `overview()` / `candidates()` read the bracket and
 *    offer the bouts these two people could be fighting: first the bout that
 *    already holds both of them, then a later-round bout still waiting on
 *    earlier results ("to be decided") that the bracket would route them into.
 *  · LINK it. `link()` files the exhibition's result into that bout through the
 *    package's own recordOutcome() — the same single door the scoring table's
 *    Commit uses — so the winner advances, the podium closes and the athletes
 *    are told exactly as for any bout. The exhibition row is kept, marked
 *    `linked`, pointing at the bout: from then on it IS that bout.
 *
 * ── What it will NOT do ─────────────────────────────────────────────────────
 *  · Put anybody into a slot the bracket holds for somebody ELSE. A side that
 *    names a different entrant is a draw edit, and draw edits have their own
 *    door (the board, under its padlock).
 *  · Fill a FIRST-ROUND slot. An empty first-round side is a bye — what the
 *    draw decided — not a place waiting on a result.
 *  · Change WHO won a bout that already has a result. A decided bout holding
 *    the same pair IS offered (kind `recorded`, 2026-10-24: on the day the
 *    results were typed into the draw by hand, often 0–0 "kiken", while the
 *    exhibition holds what was really scored) — but only when the draw's
 *    winner is the exhibition's winner. Linking it attaches the exhibition and
 *    writes the real score and reason; the winner, the bracket and the podium
 *    do not move. A draw with the OTHER winner is shown, never linked:
 *    correcting a result is the board's job, not a side effect of a link.
 *  · Fill an empty slot with somebody the bracket could never send there: the
 *    entrant must stand in the half of the bracket that feeds that side, and
 *    must not already have lost in it. That is the inverse of the same routing
 *    rule Advancement uses (bout i feeds bout ⌊i/2⌋ of the next round), so when
 *    the earlier bout is decided later, propagation finds the same person
 *    already there and leaves the linked result alone.
 *
 * Authorisation is the caller's (the console asks EventAccess::canManage).
 */
class ExhibitionLink
{
    /** The bronze play-off is fed from the semi-finals, never by routing. */
    private const ROUND_THIRD = 'Third place';

    /**
     * Every exhibition of the event, each with the draw bouts it could be.
     *
     * @return array{exhibitions: array<int, array>, matched: int}
     */
    public function overview(ClubEvent $event): array
    {
        $draw = $this->draw($event);

        $rows = EventExhibitionBout::where('event_id', $event->id)
            ->with('linkedMatch.category:id,name,weight_class')
            ->orderByDesc('number')
            ->limit(500)
            ->get()
            ->map(function (EventExhibitionBout $ex) use ($draw) {
                $candidates = $ex->status === EventExhibitionBout::STATUS_DONE
                    ? $this->fits($draw, $ex)
                    : [];

                return $this->exRow($ex) + [
                    'candidates' => $candidates,
                    // Where the draw should open when the organiser picks the
                    // bout on the board: the first suggestion's division, else
                    // a division either of the two is drawn in.
                    'division_id' => $candidates[0]['division_id'] ?? $draw['bouts']->first(fn (EventMatch $m) => in_array((int) $m->a_competitor_id, [(int) $ex->a_registration_id, (int) $ex->b_registration_id], true)
                        || in_array((int) $m->b_competitor_id, [(int) $ex->a_registration_id, (int) $ex->b_registration_id], true))?->category_id,
                ];
            });

        return [
            'exhibitions' => $rows->values()->all(),
            // "Exhibitions that have a bout waiting for them in the draw" —
            // the number the tab leads with.
            'matched' => $rows->filter(fn ($r) => collect($r['candidates'])
                ->contains(fn ($c) => $c['kind'] !== 'recorded' || $c['agrees']))->count(),
        ];
    }

    /**
     * Link mode on the draw board (owner, 2026-10-24: "take me to the draw,
     * when I select a bout ask me if I am sure"). What the board needs to run
     * it for one exhibition, or null when there is nothing to link — a bad or
     * foreign uuid, a running or already-linked exhibition, exhibitions off,
     * or a viewer who may not manage the event. Null draws the board exactly as
     * it always was.
     */
    public function picker(string $eventUuid, ?string $exUuid, ?string $mat = null): ?array
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! config('events.exhibitions', false) || ! $user || ! $exUuid || ! \Illuminate\Support\Str::isUuid($exUuid)) {
            return null;
        }

        $event = ClubEvent::where('uuid', $eventUuid)->first();

        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return null;
        }

        $ex = EventExhibitionBout::where('event_id', $event->id)->where('uuid', $exUuid)
            ->whereIn('status', [EventExhibitionBout::STATUS_DONE, EventExhibitionBout::STATUS_ABANDONED])
            ->first();

        if (! $ex || $event->sport !== 'karate') {
            return null;
        }

        $draw = $this->draw($event);

        // Back to the console the organiser came from — its mat only when the
        // event really runs one by that name.
        $courts = $draw['bouts']->pluck('court')->filter()->unique()->values()->all();
        $back = route('karate-scoreboard.control', $event->uuid)
            .($mat !== null && in_array($mat, $courts, true) ? '?mat='.rawurlencode($mat) : '');

        return [
            'code' => $ex->code(),
            'status' => $ex->status,
            'a' => ['name' => $ex->a_name, 'score' => $ex->a_score],
            'b' => ['name' => $ex->b_name, 'score' => $ex->b_score],
            'winner' => $ex->winner,
            'candidates' => collect($ex->status === EventExhibitionBout::STATUS_DONE ? $this->fits($draw, $ex) : [])
                ->mapWithKeys(fn ($c) => [$c['id'] => ['kind' => $c['kind'], 'agrees' => $c['agrees'] ?? true, 'swap' => $c['swap']]])
                ->all(),
            'taken' => array_keys($draw['taken']),
            'link_url' => route('karate-scoreboard.exhibition.link', [$event->uuid, $ex->uuid]),
            'back_url' => $back,
        ];
    }

    /** The draw bouts one exhibition could be, best first. */
    public function candidates(ClubEvent $event, EventExhibitionBout $ex): array
    {
        return $ex->status === EventExhibitionBout::STATUS_DONE
            ? $this->fits($this->draw($event), $ex)
            : [];
    }

    /**
     * File the exhibition's result into a draw bout and mark it linked.
     *
     * @return array{exhibition: array, match: array}
     *
     * @throws \RuntimeException with a message fit to show the organiser
     * @throws EventNotUnderWay when the competition has not been started
     */
    public function link(ClubEvent $event, EventExhibitionBout $ex, int $matchId, User $actor, bool $attachOnly = false): array
    {
        // A result cannot be filed against a competition nobody has started —
        // the same rule, and the same exception, as the scoring table's Commit.
        app(ResultFiling::class)->refuseUnlessUnderWay($event);

        $out = DB::transaction(function () use ($event, $ex, $matchId, $actor, $attachOnly) {
            // Re-read under the lock: two organisers linking the same
            // exhibition at once must not file it twice.
            $ex = EventExhibitionBout::where('event_id', $event->id)->lockForUpdate()->findOrFail($ex->id);

            if ($ex->status === EventExhibitionBout::STATUS_LINKED) {
                throw new \RuntimeException(__('events::events.ex_link_already'));
            }

            // Decided against the bracket as it stands NOW, not as it stood
            // when the organiser's list was drawn.
            $draw = $this->draw($event);
            $fit = $ex->status === EventExhibitionBout::STATUS_DONE && in_array($ex->winner, ['a', 'b'], true)
                ? collect($this->fits($draw, $ex))->firstWhere('id', $matchId)
                : null;

            // Any bout the organiser picks by hand (owner, 2026-10-24: "let me
            // link it to any bout I want"). The system cannot prove which
            // corner is whom there, so it touches nothing in the draw: it only
            // attaches the exhibition, its footage and its score log.
            if (! $fit && $attachOnly) {
                return $this->attachOnly($event, $ex, $matchId, $draw, $actor);
            }

            if ($ex->status !== EventExhibitionBout::STATUS_DONE || ! in_array($ex->winner, ['a', 'b'], true)) {
                throw new \RuntimeException(__('events::events.ex_link_not_done'));
            }

            if (! $fit) {
                throw new \RuntimeException(__('events::events.ex_link_no_fit'));
            }

            $match = EventMatch::where('event_id', $event->id)->lockForUpdate()->findOrFail($matchId);

            if ($fit['kind'] === 'recorded') {
                return $this->attach($event, $ex, $match, $fit, $actor);
            }

            // Seat anybody the bout was still waiting on — only where fits()
            // proved the bracket would route them there.
            foreach ($fit['fill'] as $side => $seat) {
                $match->{$side.'_competitor_id'} = $seat['competitor_id'];
                $match->{$side.'_name'} = $seat['name'];
                $match->{$side.'_country'} = $seat['country'];
                $match->{$side.'_provisional'} = $seat['provisional'];
                $match->{$side.'_score'} = null;
            }
            if ($fit['fill'] !== []) {
                $match->save();
            }

            // The exhibition's corner a stands on the bout's side a unless the
            // bout holds them the other way round.
            $map = $fit['swap'] ? ['a' => 'b', 'b' => 'a'] : ['a' => 'a', 'b' => 'b'];
            $scores = [$map['a'] => $ex->a_score, $map['b'] => $ex->b_score];

            $result = app(EventTypeRegistry::class)->for($event)->recordOutcome($event, $match->id, [
                'winner' => $map[$ex->winner],
                'a_score' => $scores['a'],
                'b_score' => $scores['b'],
                'win_reason' => $ex->win_reason,
                'win_note' => $ex->win_note,
                'status' => 'done',
            ]);

            if (($result['ok'] ?? true) === false) {
                throw new \RuntimeException((string) ($result['message'] ?? __('events::events.ex_link_no_fit')));
            }

            $ex->forceFill([
                'status' => EventExhibitionBout::STATUS_LINKED,
                'linked_match_id' => $match->id,
                'linked_by' => $actor->id,
                'linked_at' => now(),
            ])->save();

            $this->moveClips($event, $ex, $match);

            // On the officiating log, against the bout it now is.
            MatchEventLog::record(
                event: $event,
                court: (string) ($ex->court ?: $match->court ?: ''),
                sport: (string) $event->sport,
                command: 'link_exhibition',
                payload: ['exhibition' => $ex->code(), 'swapped' => $fit['swap'], 'seated' => array_keys($fit['fill'])],
                matchId: $match->id,
                scoreA: (float) ($scores['a'] ?? 0),
                scoreB: (float) ($scores['b'] ?? 0),
            );

            return [
                'exhibition' => $this->exRow($ex->load('linkedMatch.category:id,name,weight_class')) + ['candidates' => []],
                'match' => $result['match'] ?? null,
            ];
        });

        // A bout the draw never numbered (a bye, an unscheduled bout) is a
        // fought bout now: it takes its place on the exhibition's mat and the
        // mat is numbered again, so it reads in order (Tournament\MatNumbering).
        if (\App\Events\Support\Tournament\MatNumbering::enabled()) {
            $bout = EventMatch::where('event_id', $event->id)->find($matchId);

            if ($bout && $bout->match_no === null) {
                DB::transaction(fn () => app(\App\Events\Support\Tournament\MatNumbering::class)
                    ->place($bout, (string) ($ex->court ?? '')));
                app(BoutNumbering::class)->announce($event);
                $out['match']['no'] = $bout->match_no;
                if (isset($out['exhibition']['linked']) && is_array($out['exhibition']['linked'])) {
                    $out['exhibition']['linked']['number'] = $bout->match_no;
                }
            }
        }

        return $out;
    }

    /**
     * Link to a bout the draw has ALREADY decided the same way: attach the
     * exhibition and write the score it really ended on. The winner is the
     * same, so nothing advances and nothing downstream is touched.
     */
    private function attach(ClubEvent $event, EventExhibitionBout $ex, EventMatch $match, array $fit, User $actor): array
    {
        if (! $fit['agrees']) {
            throw new \RuntimeException(__('events::events.ex_link_disagrees'));
        }

        $map = $fit['swap'] ? ['a' => 'b', 'b' => 'a'] : ['a' => 'a', 'b' => 'b'];
        $before = ['a' => $match->a_score, 'b' => $match->b_score, 'reason' => $match->win_reason, 'note' => $match->win_note];

        $match->{$map['a'].'_score'} = $ex->a_score;
        $match->{$map['b'].'_score'} = $ex->b_score;
        $match->win_reason = $ex->win_reason;
        $match->win_note = $ex->win_note;
        $match->save();

        $ex->forceFill([
            'status' => EventExhibitionBout::STATUS_LINKED,
            'linked_match_id' => $match->id,
            'linked_by' => $actor->id,
            'linked_at' => now(),
        ])->save();

        $this->moveClips($event, $ex, $match);

        MatchEventLog::record(
            event: $event,
            court: (string) ($ex->court ?: $match->court ?: ''),
            sport: (string) $event->sport,
            command: 'link_exhibition',
            payload: ['exhibition' => $ex->code(), 'swapped' => $fit['swap'], 'recorded' => true, 'before' => $before],
            matchId: $match->id,
            scoreA: (float) ($match->a_score ?? 0),
            scoreB: (float) ($match->b_score ?? 0),
        );

        return [
            'exhibition' => $this->exRow($ex->load('linkedMatch.category:id,name,weight_class')) + ['candidates' => []],
            'match' => ['id' => $match->id, 'no' => $match->match_no, 'winner' => $match->winner,
                'a' => ['score' => $match->a_score], 'b' => ['score' => $match->b_score]],
        ];
    }

    /**
     * Attach an exhibition to a bout the organiser chose, without writing to
     * the draw: the competitors and the result of that bout stay exactly as
     * they are. What moves is the exhibition's identity — from now on its
     * footage and score log are that bout's.
     */
    private function attachOnly(ClubEvent $event, EventExhibitionBout $ex, int $matchId, array $draw, User $actor): array
    {
        if (! in_array($ex->status, [EventExhibitionBout::STATUS_DONE, EventExhibitionBout::STATUS_ABANDONED], true)) {
            throw new \RuntimeException(__('events::events.ex_link_not_done'));
        }

        $match = EventMatch::where('event_id', $event->id)->find($matchId);

        if (! $match) {
            throw new \RuntimeException(__('events::events.ex_link_no_fit'));
        }

        // One exhibition per bout: a second would give the bout two videos
        // and two score logs, and nobody could say which is the fight.
        if (isset($draw['taken'][$match->id])) {
            throw new \RuntimeException(__('events::events.ex_link_taken'));
        }

        $ex->forceFill([
            'status' => EventExhibitionBout::STATUS_LINKED,
            'linked_match_id' => $match->id,
            'linked_by' => $actor->id,
            'linked_at' => now(),
        ])->save();

        $this->moveClips($event, $ex, $match);

        MatchEventLog::record(
            event: $event,
            court: (string) ($ex->court ?: $match->court ?: ''),
            sport: (string) $event->sport,
            command: 'link_exhibition',
            payload: ['exhibition' => $ex->code(), 'manual' => true, 'swapped' => $this->swapped($ex, $match)],
            matchId: $match->id,
            scoreA: (float) ($match->a_score ?? 0),
            scoreB: (float) ($match->b_score ?? 0),
        );

        return [
            'exhibition' => $this->exRow($ex->load('linkedMatch.category:id,name,weight_class')) + ['candidates' => []],
            'match' => ['id' => $match->id, 'no' => $match->match_no, 'winner' => $match->winner,
                'a' => ['score' => $match->a_score], 'b' => ['score' => $match->b_score]],
        ];
    }

    /**
     * Did the exhibition's red corner stand on the bout's side b?
     *
     * By the entry first, then by the PERSON behind the entry (a duplicate
     * entry of the same member), then by the name — and when none of those
     * says, the exhibition's corners are taken as the bout's.
     */
    public function swapped(EventExhibitionBout $ex, EventMatch $match): bool
    {
        $a = (int) $ex->a_registration_id;
        $b = (int) $ex->b_registration_id;
        $ma = (int) $match->a_competitor_id;
        $mb = (int) $match->b_competitor_id;

        if ($a && ($a === $ma || $b === $mb)) {
            return false;
        }
        if ($a && ($a === $mb || $b === $ma)) {
            return true;
        }

        $users = \App\Events\Models\ClubEventRegistration::whereIn('id', array_filter([$a, $b, $ma, $mb]))
            ->pluck('user_id', 'id');
        $u = fn (int $id) => $id ? ($users[$id] ?? null) : null;

        if ($u($a) && ($u($a) === $u($ma) || ($u($b) && $u($b) === $u($mb)))) {
            return false;
        }
        if ($u($a) && ($u($a) === $u($mb) || ($u($b) && $u($b) === $u($ma)))) {
            return true;
        }

        $n = fn (?string $v) => mb_strtolower(trim((string) $v));

        return $n($ex->a_name) !== '' && ($n($ex->a_name) === $n($match->b_name) || $n($ex->b_name) === $n($match->a_name));
    }

    /* ---------------- Undoing a link ---------------- */

    /**
     * Undo a link made by mistake (owner, 2026-10-24). Undoes exactly what the
     * link did, read back from its own `link_exhibition` log row:
     *
     *  · attached only     → nothing in the draw to undo;
     *  · already recorded  → the draw's previous score and reason come back
     *                        (the winner never moved);
     *  · result filed      → the bout's result is cleared through the type's
     *                        own recordOutcome() and anybody the link SEATED is
     *                        taken out again — refused when the winner has
     *                        already fought the next bout, because clearing
     *                        would wipe that result too.
     *
     * Then the exhibition is an exhibition again: its status, its clips and
     * its score log go back to it.
     *
     * @return array{exhibition: array, message: string}
     */
    public function unlink(ClubEvent $event, EventExhibitionBout $ex, User $actor): array
    {
        return DB::transaction(function () use ($event, $ex, $actor) {
            $ex = EventExhibitionBout::where('event_id', $event->id)->lockForUpdate()->findOrFail($ex->id);

            if ($ex->status !== EventExhibitionBout::STATUS_LINKED) {
                throw new \RuntimeException(__('events::events.ex_unlink_not_linked'));
            }

            $match = $ex->linked_match_id
                ? EventMatch::where('event_id', $event->id)->lockForUpdate()->find($ex->linked_match_id)
                : null;

            // What the link did, in its own words.
            $how = $match ? \App\Events\Models\EventMatchEvent::where('event_id', $event->id)
                ->where('match_id', $match->id)
                ->where('command', 'link_exhibition')
                ->orderByDesc('id')
                ->get()
                ->first(fn ($r) => ($r->payload['exhibition'] ?? null) === $ex->code())?->payload : null;

            $undone = 'detached';

            if ($match && $how && ! empty($how['recorded'])) {
                $before = (array) ($how['before'] ?? []);
                $match->forceFill([
                    'a_score' => $before['a'] ?? null,
                    'b_score' => $before['b'] ?? null,
                    'win_reason' => $before['reason'] ?? null,
                    'win_note' => $before['note'] ?? null,
                ])->save();
                $undone = 'restored';
            } elseif ($match && $how && empty($how['manual']) && $match->winner) {
                $this->clearFiled($event, $match, (array) ($how['seated'] ?? []));
                $undone = 'cleared';
            }

            if ($match) {
                $this->returnClips($event, $ex, $match);

                MatchEventLog::record(
                    event: $event,
                    court: (string) ($ex->court ?: $match->court ?: ''),
                    sport: (string) $event->sport,
                    command: 'unlink_exhibition',
                    payload: ['exhibition' => $ex->code(), 'undone' => $undone],
                    matchId: $match->id,
                    scoreA: (float) ($match->a_score ?? 0),
                    scoreB: (float) ($match->b_score ?? 0),
                );
            }

            $ex->forceFill([
                'status' => in_array($ex->winner, ['a', 'b'], true) ? EventExhibitionBout::STATUS_DONE : EventExhibitionBout::STATUS_ABANDONED,
                'linked_match_id' => null,
                'linked_by' => null,
                'linked_at' => null,
            ])->save();

            return [
                'exhibition' => $this->exRow($ex->load('linkedMatch')) + ['candidates' => []],
                'message' => __('events::events.ex_unlinked_'.$undone, [
                    'code' => $ex->code(),
                    'no' => $match?->match_no ?? '—',
                ]),
            ];
        });
    }

    /**
     * Clear a result a link filed, and take out anybody it seated.
     */
    private function clearFiled(ClubEvent $event, EventMatch $match, array $seated): void
    {
        app(ResultFiling::class)->refuseUnlessUnderWay($event);

        $draw = $this->draw($event);
        $rounds = $draw['rounds'][$match->category_id] ?? null;
        [$r, $j] = $rounds ? $this->position($rounds, $match) : [null, null];

        // The bout the winner went on to: already decided means clearing this
        // one would wipe a result nobody asked to touch.
        $next = ($r !== null) ? $rounds->get($r + 1)?->get(intdiv($j, 2)) : null;
        if ($next && $next->winner) {
            throw new \RuntimeException(__('events::events.ex_unlink_next_decided', ['no' => $next->match_no ?? '—']));
        }

        $result = app(EventTypeRegistry::class)->for($event)->recordOutcome($event, $match->id, [
            'winner' => null, 'a_score' => null, 'b_score' => null, 'status' => 'upcoming',
        ]);
        if (($result['ok'] ?? true) === false) {
            throw new \RuntimeException((string) ($result['message'] ?? __('events::events.ex_link_no_fit')));
        }

        // Take a seated corner out again — unless its own earlier bout has
        // since been decided and sends that very person here anyway.
        $match->refresh();
        foreach (array_intersect($seated, ['a', 'b']) as $side) {
            $feeder = ($r !== null && $r > 0) ? $rounds->get($r - 1)?->get(2 * $j + ($side === 'b' ? 1 : 0)) : null;
            if ($feeder && $feeder->winner
                && (int) $feeder->{$feeder->winner.'_competitor_id'} === (int) $match->{$side.'_competitor_id'}) {
                continue;
            }
            $match->{$side.'_competitor_id'} = null;
            $match->{$side.'_name'} = null;
            $match->{$side.'_country'} = null;
            $match->{$side.'_provisional'} = false;
            $match->{$side.'_score'} = null;
        }
        $match->save();
    }

    /** The exhibition's clips go back to being the exhibition's. */
    private function returnClips(ClubEvent $event, EventExhibitionBout $ex, EventMatch $match): void
    {
        $ids = \App\Events\Models\EventCameraClip::where('event_id', $event->id)
            ->where('match_id', $match->id)
            ->where('local_ref', 'like', '%bout-ex'.(int) $ex->number.'%')
            ->get(['id', 'local_ref'])
            ->filter(fn ($c) => self::exhibitionNumber((string) $c->local_ref) === (int) $ex->number)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            \App\Events\Models\EventCameraClip::whereIn('id', $ids)->update(['match_id' => null]);
        }
    }

    /* ---------------- The footage follows the bout ---------------- */

    /**
     * The mat cameras filed the exhibition's clips with no bout ("…_bout-ex8_…"),
     * so the bout cam kept showing them as an exhibition. From the link on they
     * are the bout's: only clips of THIS event, still without a bout, carrying
     * THIS exhibition's number.
     */
    public function moveClips(ClubEvent $event, EventExhibitionBout $ex, EventMatch $match): int
    {
        $ids = \App\Events\Models\EventCameraClip::where('event_id', $event->id)
            ->whereNull('match_id')
            ->where('local_ref', 'like', '%bout-ex'.(int) $ex->number.'%')
            ->get(['id', 'local_ref'])
            ->filter(fn ($c) => self::exhibitionNumber((string) $c->local_ref) === (int) $ex->number)
            ->pluck('id');

        return $ids->isEmpty() ? 0 : \App\Events\Models\EventCameraClip::whereIn('id', $ids)->update(['match_id' => $match->id]);
    }

    /** The exhibition number a camera file name carries ("…_bout-ex4_…"), or null. */
    public static function exhibitionNumber(string $ref): ?int
    {
        return preg_match('/(?:^|_)bout-ex(\d+)(?:_|\.mp4$)/i', $ref, $m) ? (int) $m[1] : null;
    }

    /**
     * The officiating log an exhibition was scored in: its mat's rows with no
     * bout id, from the moment it went on the mat to the moment it finished.
     */
    public function logRows(EventExhibitionBout $ex): Collection
    {
        $from = \Illuminate\Support\Carbon::parse($ex->created_at);
        $to = $ex->finished_at ? \Illuminate\Support\Carbon::parse($ex->finished_at) : $from->copy()->addHour();

        return DB::table('event_match_events')
            ->where('event_id', $ex->event_id)
            ->whereNull('match_id')
            ->where('court', $ex->court)
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s'), $to->copy()->addSecond()->format('Y-m-d H:i:s')])
            ->orderBy('occurred_at')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * For a draw bout an exhibition was linked to: that exhibition's log, turned
     * to the bout's corners — what was really scored on the mat. Null for any
     * other bout, which keeps reading its own log exactly as before.
     */
    public function rowsForBout(EventMatch $match): ?Collection
    {
        if (! $match->id) {
            return null;
        }

        $ex = EventExhibitionBout::where('linked_match_id', $match->id)
            ->where('status', EventExhibitionBout::STATUS_LINKED)
            ->first();

        if (! $ex) {
            return null;
        }

        $rows = $this->logRows($ex);

        // Fought the other way round: the bout's corner a was the exhibition's b.
        if ($this->swapped($ex, $match)) {
            $rows = $rows->map(function ($r) {
                $r = clone $r;
                $r->side = match ($r->side ?? null) { 'a' => 'b', 'b' => 'a', default => $r->side ?? null };
                [$r->score_a, $r->score_b] = [$r->score_b, $r->score_a];

                return $r;
            });
        }

        return $rows;
    }

    /* ---------------- Reading the bracket ---------------- */

    /**
     * The draw, once: every bout, and each division's rounds in bracket order.
     *
     * @return array{bouts: Collection<int, EventMatch>, rounds: array<int, Collection>}
     */
    private function draw(ClubEvent $event): array
    {
        $bouts = EventMatch::where('event_id', $event->id)
            ->whereNotNull('category_id')
            ->with('category:id,name,weight_class,is_heading')
            ->orderBy('slot')
            ->get();

        // Exactly Advancement::rounds(): grouped by round, ordered by the
        // earliest slot, the bronze play-off set aside.
        $rounds = $bouts->groupBy('category_id')->map(fn (Collection $c) => $c
            ->reject(fn (EventMatch $m) => $m->round === self::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn (Collection $r) => $r->min('slot'))
            ->map(fn (Collection $r) => $r->values())
            ->values())->all();

        // A bout another exhibition is already linked to is taken.
        $taken = EventExhibitionBout::where('event_id', $event->id)
            ->where('status', EventExhibitionBout::STATUS_LINKED)
            ->whereNotNull('linked_match_id')
            ->pluck('linked_match_id')->map(fn ($id) => (int) $id)->all();

        return ['bouts' => $bouts, 'rounds' => $rounds, 'taken' => array_flip($taken)];
    }

    /**
     * The undecided bouts these two entrants could be, best first.
     *
     * @return array<int, array>
     */
    private function fits(array $draw, EventExhibitionBout $ex): array
    {
        $x = $ex->a_registration_id;
        $y = $ex->b_registration_id;

        if (! $x || ! $y) {
            return [];
        }

        $out = [];

        foreach ($draw['bouts'] as $m) {
            if (! $m->category || $m->category->isHeading() || isset($draw['taken'][$m->id])) {
                continue;
            }

            // Already decided: offered only when it is this very pair.
            if ($m->winner || $m->status === 'done') {
                $pair = [(int) $m->a_competitor_id, (int) $m->b_competitor_id];
                if ($pair !== [(int) $x, (int) $y] && $pair !== [(int) $y, (int) $x]) {
                    continue;
                }

                $swap = $pair[0] !== (int) $x;
                $exWinner = $ex->winner ? ($swap ? ($ex->winner === 'a' ? 'b' : 'a') : $ex->winner) : null;

                $out[] = $this->candidate($m, 'recorded', $swap, []) + [
                    'agrees' => $exWinner !== null && $exWinner === $m->winner,
                    'recorded' => ['a' => $m->a_score, 'b' => $m->b_score, 'winner' => $m->winner, 'reason' => $m->win_reason],
                ];

                continue;
            }

            // A bout with neither of them in it is only worth asking about when
            // it is still entirely to be decided.
            $held = [$m->a_competitor_id, $m->b_competitor_id];
            if (array_filter($held) !== [] && ! in_array($x, $held) && ! in_array($y, $held)) {
                continue;
            }

            $best = null;
            foreach ([false, true] as $swap) {
                $want = $swap ? ['a' => $y, 'b' => $x] : ['a' => $x, 'b' => $y];
                $fill = [];

                foreach ($want as $side => $who) {
                    if ($m->{$side.'_competitor_id'}) {
                        if ((int) $m->{$side.'_competitor_id'} !== (int) $who) {
                            continue 2;
                        }

                        continue;
                    }

                    // A typed-in name with no entry behind it is somebody.
                    if ($m->{$side.'_name'}) {
                        continue 2;
                    }

                    $seat = $this->seat($draw, $m, $side, (int) $who);
                    if (! $seat) {
                        continue 2;
                    }
                    $fill[$side] = $seat;
                }

                if (! $best || count($fill) < count($best['fill'])) {
                    $best = ['swap' => $swap, 'fill' => $fill];
                }
            }

            if (! $best) {
                continue;
            }

            // exact: both already in the bout · seat: the bout is still
            // waiting on an earlier result for one or both corners.
            $out[] = $this->candidate($m, $best['fill'] === [] ? 'exact' : 'seat', $best['swap'], $best['fill']);
        }

        $rank = ['exact' => 0, 'recorded' => 1, 'seat' => 2];
        usort($out, fn ($p, $q) => [$rank[$p['kind']], count($p['fill']), $p['number'] ?? PHP_INT_MAX, $p['id']]
            <=> [$rank[$q['kind']], count($q['fill']), $q['number'] ?? PHP_INT_MAX, $q['id']]);

        return $out;
    }

    /** One draw bout, as a candidate. */
    private function candidate(EventMatch $m, string $kind, bool $swap, array $fill): array
    {
        return [
            'id' => $m->id,
            'number' => $m->match_no,
            'round' => (string) ($m->round ?: $m->phase ?: ''),
            'division' => (string) ($m->category->weight_class ?: $m->category->name),
            'court' => $m->court,
            'division_id' => $m->category_id,
            'kind' => $kind,
            'swap' => $swap,
            'fill' => $fill,
            'a' => $m->a_name ?: ($fill['a']['name'] ?? null),
            'b' => $m->b_name ?: ($fill['b']['name'] ?? null),
        ];
    }

    /**
     * May $who be seated on $side of $m, and as what?
     *
     * Only when the bracket would route them there: the side is fed by an
     * earlier bout, $who stands somewhere in the bouts that feed it, and has
     * not lost any of them that is already decided.
     *
     * @return array{competitor_id: int, name: string, country: ?string, provisional: bool}|null
     */
    private function seat(array $draw, EventMatch $m, string $side, int $who): ?array
    {
        $rounds = $draw['rounds'][$m->category_id] ?? null;
        if (! $rounds) {
            return null;
        }

        [$r, $j] = $this->position($rounds, $m);
        if ($r === null || $r === 0) {
            return null; // not routed (bronze play-off) or a first-round bye
        }

        $found = null;
        foreach ($this->subtree($rounds, $r - 1, 2 * $j + ($side === 'b' ? 1 : 0)) as $bout) {
            foreach (['a', 'b'] as $s) {
                if ((int) $bout->{$s.'_competitor_id'} !== $who) {
                    continue;
                }

                // Already beaten on the way here: the bracket sends somebody else.
                if ($bout->winner && $bout->winner !== $s) {
                    return null;
                }

                $found ??= [
                    'competitor_id' => $who,
                    'name' => (string) $bout->{$s.'_name'},
                    'country' => $bout->{$s.'_country'},
                    'provisional' => (bool) $bout->{$s.'_provisional'},
                ];
            }
        }

        return $found && $found['name'] !== '' ? $found : null;
    }

    /** [round index, index within the round] of a bout, or [null, null]. */
    private function position(Collection $rounds, EventMatch $m): array
    {
        foreach ($rounds as $r => $bouts) {
            $j = $bouts->search(fn (EventMatch $b) => $b->id === $m->id);
            if ($j !== false) {
                return [$r, $j];
            }
        }

        return [null, null];
    }

    /**
     * The bout at ($r, $j) and every bout that feeds it.
     *
     * @return array<int, EventMatch>
     */
    private function subtree(Collection $rounds, int $r, int $j): array
    {
        $bout = $rounds->get($r)?->get($j);
        if (! $bout) {
            return [];
        }

        if ($r === 0) {
            return [$bout];
        }

        return array_merge([$bout], $this->subtree($rounds, $r - 1, 2 * $j), $this->subtree($rounds, $r - 1, 2 * $j + 1));
    }

    /** One exhibition, as the console's list shows it. */
    private function exRow(EventExhibitionBout $ex): array
    {
        $linked = $ex->linkedMatch;

        return [
            'id' => $ex->id,
            'uuid' => $ex->uuid,
            'code' => $ex->code(),
            'court' => $ex->court,
            'status' => $ex->status,
            'a' => ['name' => $ex->a_name, 'score' => $ex->a_score, 'entry' => $ex->a_registration_id],
            'b' => ['name' => $ex->b_name, 'score' => $ex->b_score, 'entry' => $ex->b_registration_id],
            'winner' => $ex->winner,
            'win_reason' => $ex->win_reason,
            'fought_at' => ($ex->finished_at ?? $ex->created_at)?->toIso8601String(),
            'linked' => $linked ? [
                'id' => $linked->id,
                'number' => $linked->match_no,
                'round' => (string) ($linked->round ?: $linked->phase ?: ''),
                'division' => (string) ($linked->category?->weight_class ?: $linked->category?->name ?: ''),
            ] : null,
        ];
    }
}
