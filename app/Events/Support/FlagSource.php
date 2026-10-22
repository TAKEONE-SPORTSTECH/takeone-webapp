<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Which flag a competitor flies at THIS event (owner, 2026-10-16): the
 * country of the club they compete for, or their own nationality.
 *
 * `club_events.flag_source` — NULL / 'club' is what every event did before
 * this setting existed, so no event changes unless its organiser chooses.
 * 'nationality' puts the passport first and falls back to the club when the
 * nationality is not on file, so nobody loses a flag by the switch.
 *
 * The answer itself stays in ONE place, `ClubEventRegistration::countryCode()`,
 * which every surface already reads (bracket, hall screens, scoreboards, entry
 * lists, podium). This class only says which way that answer leans, and keeps
 * the stored copy on each bout (`event_matches.{a,b}_country`) in step.
 */
class FlagSource
{
    public const CLUB = 'club';

    public const NATIONALITY = 'nationality';

    public const ALL = [self::CLUB, self::NATIONALITY];

    /**
     * Does this event fly nationalities? Asked once per registration on every
     * board, so it is cached per event — across processes, because the hall
     * screens and the queue read it too — and forgotten the moment it changes.
     */
    public static function byNationality(?int $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        return (bool) Cache::rememberForever(self::key($eventId), fn () => DB::table('club_events')
            ->where('id', $eventId)->value('flag_source') === self::NATIONALITY);
    }

    public static function of(ClubEvent $event): string
    {
        return $event->flag_source === self::NATIONALITY ? self::NATIONALITY : self::CLUB;
    }

    /**
     * Change the event's choice, re-copy the flag onto its bouts, and tell
     * every open screen to re-read. Results, numbers and corners are never
     * touched — only the two denormalised country columns.
     *
     * @return int how many bout corners had their flag rewritten
     */
    public function set(ClubEvent $event, string $source): int
    {
        $source = in_array($source, self::ALL, true) ? $source : self::CLUB;

        $event->forceFill(['flag_source' => $source])->save();
        Cache::forget(self::key($event->id));

        $changed = DB::transaction(fn () => $this->recopy($event));

        $this->announce($event->fresh());

        return $changed;
    }

    /** The stored `{a,b}_country` copy on each bout, re-taken from countryCode(). */
    private function recopy(ClubEvent $event): int
    {
        $regs = ClubEventRegistration::with(['representingTenant:id,country', 'user:id,nationality'])
            ->where('event_id', $event->id)->get()->keyBy('id');

        $changed = 0;
        EventMatch::where('event_id', $event->id)->get()->each(function (EventMatch $m) use ($regs, &$changed) {
            $dirty = false;
            foreach (['a', 'b'] as $side) {
                $reg = $regs->get($m->{$side.'_competitor_id'});
                if (! $reg) {
                    continue;
                }
                $code = $reg->countryCode();
                if ($code && $code !== $m->{$side.'_country'}) {
                    $m->{$side.'_country'} = $code;
                    $dirty = true;
                    $changed++;
                }
            }
            if ($dirty) {
                $m->saveQuietly();
            }
        });

        return $changed;
    }

    /** Brackets, the wall and the mats re-read. Best-effort, like renumbering. */
    private function announce(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            $type = app(\App\Events\EventTypeRegistry::class)->for($event);
            if (method_exists($type, 'announceDrawChanged')) {
                $type->announceDrawChanged($event);
            }
        }, null, false);
        rescue(fn () => HallWalls::refresh($event), null, false);
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);
    }

    private static function key(int $eventId): string
    {
        return 'event:'.$eventId.':flag_source';
    }
}
