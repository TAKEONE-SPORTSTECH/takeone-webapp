<?php

namespace App\Ranks\Support;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\Tenant;
use App\Ranks\Models\ActivityPackage;
use App\Sports\Package\RuleBook;
use App\Sports\Package\SportPackages;
use Illuminate\Support\Facades\Log;

/**
 * The link between a club activity and its sport package — the one place that
 * answers "is this activity a Karate class, and does it have ranks?".
 *
 * The match is automatic (owner's decision, 2026-09-30): read from the
 * activity's name and style. A club's own choice ('club') always wins and is
 * never redone; the platform's ('auto') is redone when the activity is renamed.
 */
final class ActivityPackages
{
    public function __construct(private SportPackages $packs) {}

    /**
     * What an activity called this is, as [sport, style] — or null when it is
     * not a sport the platform has a package for.
     *
     * @return array{0: string, 1: string}|null
     */
    public function match(?string $name, ?string $style = null): ?array
    {
        $t = ' '.preg_replace('/[^a-z0-9]+/', ' ', strtolower(trim(($name ?? '').' '.($style ?? '')))).' ';

        $hit = match (true) {
            str_contains($t, 'taekwondo') || str_contains($t, 'tae kwon do') || str_contains($t, 'taekwon do') || str_contains($t, ' tkd ') => ['taekwondo', 'wt'],
            str_contains($t, 'karate') => ['karate', str_contains($t, 'shotokan') ? 'shotokan' : 'wkf'],
            str_contains($t, 'jiu jitsu') || str_contains($t, 'jiujitsu') || str_contains($t, 'jujitsu') || str_contains($t, 'ju jitsu') || str_contains($t, ' bjj ') => ['bjj', 'ibjjf'],
            default => null,
        };

        // Only ever a style the package actually has.
        return $hit && $this->packs->style($hit[0], $hit[1]) ? $hit : null;
    }

    /**
     * Link (or re-link) an activity after it is saved. Never overrides a club's
     * own choice, and never throws: an activity must always save.
     */
    public function autoLink(ClubActivity $activity): void
    {
        try {
            $link = ActivityPackage::where('activity_id', $activity->id)->first();
            if ($link && $link->matched_by === 'club') {
                return;
            }

            $hit = $this->match($this->plain($activity->getRawOriginal('name') ?? $activity->name), $this->plain($activity->getRawOriginal('style') ?? $activity->style));
            if (! $hit) {
                // Kept as a row with no sport: the activity still has a key the
                // club can use to choose a package for it by hand.
                ActivityPackage::updateOrCreate(['activity_id' => $activity->id], ['sport' => '', 'style' => '', 'matched_by' => 'auto']);

                return;
            }

            ActivityPackage::updateOrCreate(
                ['activity_id' => $activity->id],
                ['sport' => $hit[0], 'style' => $hit[1], 'matched_by' => 'auto'],
            );
        } catch (\Throwable $e) {
            Log::warning('Activity package link failed', ['activity' => $activity->id, 'error' => $e->getMessage()]);
        }
    }

    /** A club's own choice — kept from then on. `null` style means "not a ranked sport". */
    public function choose(ClubActivity $activity, ?string $sport, ?string $style): void
    {
        if ($sport === null) {
            ActivityPackage::updateOrCreate(['activity_id' => $activity->id], ['sport' => '', 'style' => '', 'matched_by' => 'club']);

            return;
        }
        abort_unless($this->packs->style($sport, (string) $style), 422);
        ActivityPackage::updateOrCreate(['activity_id' => $activity->id], ['sport' => $sport, 'style' => (string) $style, 'matched_by' => 'club']);
    }

    /** The rule book an activity runs under, or null when it has no package. */
    public function ruleBook(ClubActivity $activity): ?RuleBook
    {
        $link = ActivityPackage::where('activity_id', $activity->id)->first();

        return $link && $link->sport !== '' ? $this->packs->style($link->sport, $link->style) : null;
    }

    /** Does this activity's sport have ranks at all? */
    public function hasRanks(ClubActivity $activity): bool
    {
        return (bool) $this->ruleBook($activity)?->hasRanks();
    }

    /**
     * The club's activities that bring ranks with them.
     *
     * @return \Illuminate\Support\Collection<int, array{activity: ClubActivity, book: RuleBook}>
     */
    public function rankedActivities(Tenant $club)
    {
        $links = ActivityPackage::query()
            ->whereIn('activity_id', ClubActivity::where('tenant_id', $club->id)->select('id'))
            ->where('sport', '<>', '')
            ->get()->keyBy('activity_id');

        return ClubActivity::where('tenant_id', $club->id)->whereIn('id', $links->keys())->orderBy('name')->get()
            ->map(fn (ClubActivity $a) => ['activity' => $a, 'book' => $this->packs->style($links[$a->id]->sport, $links[$a->id]->style)])
            ->filter(fn ($row) => $row['book']?->hasRanks())
            ->values();
    }

    public function clubHasRanks(Tenant $club): bool
    {
        return $this->rankedActivities($club)->isNotEmpty();
    }

    /** A translatable column's plain text (the name may be stored as a JSON of languages). */
    private function plain(mixed $v): string
    {
        if (is_string($v) && str_starts_with(ltrim($v), '{')) {
            $d = json_decode($v, true);
            if (is_array($d)) {
                return implode(' ', array_filter(array_map('strval', $d)));
            }
        }

        return (string) $v;
    }
}
