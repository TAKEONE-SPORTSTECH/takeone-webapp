<?php

namespace App\Federations\Support;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationFee;
use App\Federations\Models\FederationFeeSeason;
use App\Federations\Services\FederationPlatformEvent;
use App\Rankings\Support\EventPoints;
use Carbon\CarbonImmutable;

/**
 * Is this club recognised by its federation on this day? — the one answer the
 * entry gate and the national ranking both ask (the public door other modules
 * call; the season/fee bookkeeping itself is FederationClubFees).
 *
 * A club is recognised on a day when a fee it owes the federation's TOP body
 * for a season covering that day (start … end + grace) is approved or waived.
 * Seasons belong to the top body: a regional sub-federation's clubs pay the
 * national one.
 *
 * Never enforced by surprise:
 *   - the switch (federations.club_fees) is off by default;
 *   - a federation that has created no season enforces nothing;
 *   - nothing dated before its FIRST season starts is ever affected, so turning
 *     it on cannot strip points already earned or refuse an entry retroactively.
 */
final class ClubRecognition
{
    /** @var array<int, ?CarbonImmutable> root id → first season start (per request) */
    private static array $from = [];

    public static function on(): bool
    {
        return (bool) config('federations.club_fees');
    }

    /** The body whose seasons apply: the top of the tree. */
    public static function root(Federation $f): Federation
    {
        $cur = $f;
        for ($i = 0; $cur->parent_id && $i < 10; $i++) {
            $up = Federation::find($cur->parent_id);
            if (! $up) {
                break;
            }
            $cur = $up;
        }

        return $cur;
    }

    /** Is recognition required for this federation on this day? */
    public static function enforced(Federation $federation, CarbonImmutable $day): bool
    {
        if (! self::on()) {
            return false;
        }
        $root = self::root($federation);
        if (! array_key_exists($root->id, self::$from)) {
            $first = FederationFeeSeason::where('federation_id', $root->id)->min('starts_on');
            self::$from[$root->id] = $first ? CarbonImmutable::parse($first)->startOfDay() : null;
        }

        return self::$from[$root->id] !== null && $day->startOfDay()->gte(self::$from[$root->id]);
    }

    /** Recognised by $federation's tree on $day? (Only meaningful where enforced.) */
    public static function recognised(Federation $federation, int $tenantId, CarbonImmutable $day): bool
    {
        $root = self::root($federation);

        return FederationFee::query()
            ->where('kind', FederationFee::KIND_CLUB)
            ->whereIn('status', FederationFee::RECOGNISING)
            ->whereHas('club', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('season', fn ($q) => $q->where('federation_id', $root->id)->where('starts_on', '<=', $day->toDateString()))
            ->with('season')
            ->get()
            ->contains(fn (FederationFee $fee) => $fee->season && $fee->season->covers($day->startOfDay()));
    }

    /**
     * Why a club may not enter this federation event, or null when it may.
     * Only RANKED events are gated (an event that grants national ranking
     * points); friendlies and club opens stay open to every club.
     */
    public static function entryRefusal(FederationEvent $event, FederationClub|FederationClubApplication|null $club): ?string
    {
        if (! self::on()) {
            return null;
        }
        $platform = rescue(fn () => app(FederationPlatformEvent::class)->of($event), null, report: false);
        if (! $platform instanceof ClubEvent || ! EventPoints::grants($platform)) {
            return null;
        }
        $federation = $event->federation;
        $day = CarbonImmutable::parse($platform->date ?? now())->startOfDay();
        if (! $federation || ! self::enforced($federation, $day)) {
            return null;
        }
        if ($club instanceof FederationClub && $club->tenant_id && self::recognised($federation, (int) $club->tenant_id, $day)) {
            return null;
        }

        return __('federations::messages.fee_entry_refused', ['federation' => self::root($federation)->name]);
    }

    /**
     * Does a result count toward the national ranking? Only if the athlete's
     * club (as the entry recorded it) was recognised on the day it was earned.
     * An entry with no recorded club is not judged — it cannot be attributed.
     */
    public static function countsForRanking(ClubEvent $event, ?int $tenantId, CarbonImmutable $day): bool
    {
        if (! self::on() || ! $tenantId || $event->host_type !== 'federation' || ! $event->host_id) {
            return true;
        }
        $federation = Federation::find($event->host_id);
        if (! $federation || ! self::enforced($federation, $day)) {
            return true;
        }

        return self::recognised($federation, $tenantId, $day);
    }

    /** Forget per-request memory (a season was just created or changed). */
    public static function forget(): void
    {
        self::$from = [];
    }
}
