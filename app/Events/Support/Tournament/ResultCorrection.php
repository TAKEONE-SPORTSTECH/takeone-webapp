<?php

namespace App\Events\Support\Tournament;

use App\Events\Contracts\AwardsDivisions;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use App\Events\Support\MatchEventLog;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taking a result back, or changing it, after the bout was fought (owner,
 * 2026-10-17: "by mistake there were bouts which we declared winners wrongly
 * … I want to be able to remove the winner and make them play again").
 *
 * The board already offered it — a tap on the winner behind the padlock posts
 * `winner: ''` to me.events.outcome — but that path had three faults this
 * class closes, for every sport, at the one door every caller goes through:
 *
 *  1. It SILENTLY WIPED LATER RESULTS. Advancement::propagate() voids every
 *     bout downstream whose competitor changes, so clearing a quarter-final
 *     whose winner had already won the semi erased the semi too, unasked.
 *     Now refused, naming the bout, until that one is corrected first.
 *  2. A cleared bout came back with the OLD SCORE on it. "Play again" is a
 *     fresh bout: the scoreline is reset — and kept, with who and why, in
 *     the officiating log, so nothing is lost.
 *  3. A cleared final kept its PODIUM (and so its medals, profiles and
 *     certificates). The podium is now re-derived the way ThirdPlaceSwitch
 *     does it.
 *
 * It never touches the mats: it refuses outright when a later bout has been
 * started on one (any score, or anything in its officiating log).
 */
final class ResultCorrection
{
    public static function enabled(): bool
    {
        return (bool) config('events.result_correction', false);
    }

    /** Is this request taking a decided bout's result away or changing it? */
    public static function changes(EventMatch $match, array $payload): bool
    {
        if (! $match->winner || ! array_key_exists('winner', $payload)) {
            return false;
        }

        $new = in_array($payload['winner'], ['a', 'b'], true) ? $payload['winner'] : null;

        return $new !== $match->winner;
    }

    /**
     * Later bouts in this division that the change would rewrite and that have
     * already been fought or started — the reason to refuse.
     *
     * @return Collection<int, EventMatch>
     */
    public static function blockers(EventMatch $match): Collection
    {
        $bouts = EventMatch::where('category_id', $match->category_id)->get();

        // Rounds in bracket order, as every Advancement reads them: a round
        // ranks by the lowest slot it holds.
        $rank = $bouts->groupBy('round')->map(fn ($r) => (int) $r->min('slot'));
        $mine = $rank[$match->round] ?? -1;

        $people = array_values(array_filter([$match->a_competitor_id, $match->b_competitor_id]));
        $names = array_values(array_filter([$match->a_name, $match->b_name]));

        $later = $bouts->filter(function (EventMatch $b) use ($match, $rank, $mine, $people, $names) {
            if ($b->id === $match->id || ($rank[$b->round] ?? -1) <= $mine) {
                return false;
            }
            foreach (['a', 'b'] as $s) {
                $id = $b->{$s.'_competitor_id'};
                if ($id ? in_array($id, $people, true) : in_array($b->{$s.'_name'}, $names, true)) {
                    return true;
                }
            }

            return false;
        });

        if ($later->isEmpty()) {
            return $later;
        }

        $logged = DB::table('event_match_events')->whereIn('match_id', $later->pluck('id'))
            ->distinct()->pluck('match_id')->all();

        return $later->filter(fn (EventMatch $b) => ($b->winner && ! $b->won_by_bye)
            || (float) $b->a_score > 0 || (float) $b->b_score > 0
            || in_array($b->id, $logged, true))->values();
    }

    /** The sentence that refuses, naming the bouts to correct first. */
    public static function refusal(Collection $blockers): string
    {
        $list = $blockers->sortByDesc(fn (EventMatch $b) => (int) $b->match_no)
            ->map(fn (EventMatch $b) => $b->match_no ? '#'.$b->match_no : $b->round)->implode(', ');

        return __('events::events.result_correction_blocked', ['bouts' => $list]);
    }

    /**
     * What a CLEAR adds to the payload: the scoreline goes, so the bout is
     * fought again from nothing. The old one is in the log (after()).
     */
    public static function clearPayload(array $payload): array
    {
        $new = in_array($payload['winner'] ?? null, ['a', 'b'], true) ? $payload['winner'] : null;
        if ($new !== null) {
            return $payload;
        }

        return ['a_score' => null, 'b_score' => null, 'status' => 'upcoming'] + $payload;
    }

    /** The bout as it stood, for the log. */
    public static function snapshot(EventMatch $match): array
    {
        return $match->only(['id', 'match_no', 'round', 'winner', 'a_name', 'b_name', 'a_score', 'b_score', 'win_reason', 'win_note', 'status']);
    }

    /**
     * After the change: write it to the officiating log, and re-derive the
     * podium if the division had one.
     */
    public static function after(ClubEvent $event, EventMatch $before, array $snapshot, ?User $by): void
    {
        $match = $before->fresh() ?? $before;

        rescue(fn () => MatchEventLog::record(
            $event,
            (string) ($match->court ?: '-'),
            (string) $event->sport,
            $match->winner ? 'result_changed' : 'result_cleared',
            ['before' => $snapshot, 'winner' => $match->winner, 'by' => $by?->id],
            $match->id,
            (float) ($snapshot['a_score'] ?? 0),
            (float) ($snapshot['b_score'] ?? 0),
        ), null, true);

        $category = $match->category()->first();
        if (! $category || empty($category->podium)) {
            return;
        }

        $type = app(\App\Events\EventTypeRegistry::class)->for($event);
        if (! $type instanceof AwardsDivisions) {
            return;
        }

        // As ThirdPlaceSwitch does: clear, then let the package award again —
        // it writes the podium back only when the division is still decided.
        $category->update(['podium' => []]);
        $podium = $type->awardDivision($category->fresh());
        if ($podium === [] && $category->fresh()->status === 'completed') {
            $category->update(['status' => 'live']);
        }

        rescue(fn () => $type->announceDrawChanged($event, $category->id), null, false);
    }
}
