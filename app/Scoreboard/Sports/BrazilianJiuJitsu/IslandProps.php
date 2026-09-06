<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu;

use App\Models\ClubEvent;
use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenBoard;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState;

/**
 * What the React islands are handed at first paint.
 *
 * Built HERE rather than in the two Blade shells so the wall board and the
 * scoring table cannot end up with different vocabularies for the same thing —
 * and so translation stays where it belongs. Not one string in the islands is
 * written in JavaScript: every word below comes from the same lang files the
 * Blade documents read, which is what keeps the Arabic build working when the
 * flag is on (CLAUDE.md → Mobile i18n).
 *
 * Nothing here is a decision. It is the same state `present()` returns, the same
 * running order `ScreenBoard` publishes and the same price list the server
 * enforces — handed over once so the island's first frame is correct without a
 * round trip, exactly as the schedule island is seeded.
 */
class IslandProps
{
    /** The words BOTH surfaces need — corners, statuses, and the shared nouns. */
    private static function common(): array
    {
        return [
            'corner_blue' => __('sport-brazilianjiujitsu::messages.corner_blue'),
            'corner_white' => __('sport-brazilianjiujitsu::messages.corner_white'),
            'tbd' => __('scoreboard::bjj_messages.court_tbd'),
            'vs' => __('scoreboard::bjj_messages.vs'),
            'advantages' => __('scoreboard::bjj_messages.advantages'),
            'penalties' => __('scoreboard::bjj_messages.penalties'),
            'adv_short' => __('scoreboard::bjj_messages.adv_short'),
            'pen_short' => __('scoreboard::bjj_messages.pen_short'),
            'court_court' => __('scoreboard::bjj_messages.court_court'),
            'court_match' => __('scoreboard::bjj_messages.court_match'),
            'statuses' => self::statuses(),
            'penalties_by_reason' => self::penaltyReasons(),
        ];
    }

    private static function statuses(): array
    {
        $out = [];

        foreach (MatState::STATUSES as $status) {
            $out[$status] = __('scoreboard::bjj_messages.status_'.$status);
        }

        return $out;
    }

    private static function penaltyReasons(): array
    {
        $out = [];

        foreach (Ledger::PENALTY_REASONS as $reason) {
            $out[$reason] = __('scoreboard::bjj_messages.penalty_'.$reason);
        }

        return $out;
    }

    private static function sources(): array
    {
        $out = [];

        // The VALUE is shown for the operator's benefit only; the server prices
        // the action itself, so nothing the island holds can change a score.
        foreach (Ledger::POINT_SOURCES as $key => $value) {
            $out[$key] = [
                'value' => $value,
                'label' => __('scoreboard::bjj_messages.source_'.$key),
            ];
        }

        return $out;
    }

    private static function methods(): array
    {
        $out = [];

        foreach (MatState::WIN_METHODS as $method) {
            $out[$method] = __('scoreboard::bjj_messages.method_'.$method);
        }

        return $out;
    }

    /**
     * The MAT SCREEN's props.
     *
     * @param  array<string, mixed>  $state    MatState::present()
     * @param  array<string, mixed>  $board    ScreenBoard::payload()
     * @param  array<string, ?string>  $urls
     */
    public static function board(ClubEvent $event, string $court, string $pinned, array $state, array $board, array $urls): array
    {
        $words = self::common() + [
            'court_title' => __('scoreboard::bjj_messages.court_title'),
            'court_idle_title' => __('scoreboard::bjj_messages.court_idle_title'),
            'court_idle_sub' => __('scoreboard::bjj_messages.court_idle_sub'),
            'court_reconnecting' => __('scoreboard::bjj_messages.court_reconnecting'),
            'vs_referee' => __('scoreboard::bjj_messages.vs_referee'),
            'get_ready' => __('scoreboard::bjj_messages.get_ready'),
            'status_warning' => __('scoreboard::bjj_messages.status_warning'),
            'regulation_time' => __('scoreboard::bjj_messages.regulation_time'),
            'overtime_time' => __('scoreboard::bjj_messages.overtime_time'),
            'penalty_notice' => __('scoreboard::bjj_messages.penalty_notice'),
            'please_wait' => __('scoreboard::bjj_messages.please_wait'),
            'winner' => __('scoreboard::bjj_messages.winner'),
            'won_by' => __('scoreboard::bjj_messages.won_by'),
            'sources' => array_map(fn (array $s) => $s['label'], self::sources()),
            'penalties' => self::penaltyReasons(),
            'methods' => self::methods(),
            'decided' => [
                'points' => __('scoreboard::bjj_messages.decided_by_points'),
                'advantages' => __('scoreboard::bjj_messages.decided_by_advantages'),
                'penalties' => __('scoreboard::bjj_messages.decided_by_penalties'),
            ],
        ];

        return [
            'surface' => 'board',
            'court' => $court,
            'pinned' => $pinned,
            'rows' => ScreenBoard::ROWS,
            'event' => [
                'title' => (string) $event->title,
                'logo' => $event->tenant?->logo ? file_url($event->tenant->logo) : null,
            ],
            'state' => $state,
            'board' => $board,
            'urls' => $urls,
            'words' => $words,
        ];
    }

    /**
     * The SCORING TABLE's props.
     *
     * @param  array<string, mixed>  $state
     * @param  array<int, array<string, mixed>>  $log
     * @param  array<int, array<string, mixed>>  $queue
     * @param  array<string, ?string>  $urls
     */
    public static function console(ClubEvent $event, string $court, string $layout, array $state, array $log, array $queue, int $screens, array $urls): array
    {
        $words = self::common() + [
            'sb_division' => __('scoreboard::bjj_messages.sb_division'),
            'penalty_stalling' => __('scoreboard::bjj_messages.penalty_stalling'),
            'penalty_next_is_dq' => __('scoreboard::bjj_messages.penalty_next_is_dq'),
            'reason_required' => __('scoreboard::bjj_messages.reason_required'),
        ];

        // Every ctl_* string, by its own key — the console's whole vocabulary,
        // so a new control never needs this list edited in two places.
        foreach ([
            'title', 'queue', 'log', 'load', 'start', 'pause', 'resume', 'end', 'winner', 'method',
            'winner_required', 'reset', 'commit', 'undo', 'undo_hint', 'review', 'medical', 'overtime',
            'stall', 'stall_cancel', 'stall_apply', 'decision', 'reason', 'confirm', 'cancel',
            'type_match_no', 'no_screens', 'screens', 'theme', 'settings', 'ruleset', 'referee',
        ] as $key) {
            $words['ctl_'.$key] = __('scoreboard::bjj_messages.ctl_'.$key);
        }

        return [
            'surface' => 'console',
            // The marker the live-link client reads to decide what to do with an
            // inbound message — the same one the Blade console declares inline.
            // A console handed a wall board's payload would draw nothing, so it
            // is stated on the page rather than only inside the bundle.
            'pinned' => 'console',
            'layout' => $layout,
            'court' => $court,
            'event' => ['title' => (string) $event->title],
            'state' => $state,
            'log' => $log,
            'queue' => $queue,
            'screens' => $screens,
            'sources' => self::sources(),
            'penalties' => self::penaltyReasons(),
            'methods' => self::methods(),
            'urls' => $urls,
            'words' => $words,
        ];
    }
}
