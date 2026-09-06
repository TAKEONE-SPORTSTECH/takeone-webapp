<?php

namespace App\Scoreboard;

use App\Models\ClubEvent;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Controllers\HallScreenController;
use App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController as KarateCourtDisplay;
use App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController as TaekwondoCourtDisplay;
use Illuminate\Http\Request;

/**
 * The module's front door for whoever is running a mat.
 *
 * A host — today that is App\Events, tomorrow it might be a hall renting the
 * table on its own — needs three things from the screens on a mat: list them,
 * pair one, unpair one. This is where it asks. It is the ONLY thing outside this
 * module that may be called, which is what makes
 * `App\Scoreboard\...\Controllers\` genuinely private rather than private by
 * convention: ModuleBoundaryTest fails the build if anything reaches past it.
 *
 * Before this, App\Events\Support\HallScreenRouter held a sport => controller
 * map and called the controller directly. That map is the reason the two
 * verticals could not be worked on separately: a change to a screen controller's
 * signature was a change to a file in the events module. It is gone — every mat
 * is behind this class now, and the router reaches exactly one thing.
 *
 * The method names deliberately match the controllers', so the router can hold
 * either this or one of its own controllers and call the same thing.
 */
class Fleet
{
    /**
     * Sports whose mats this module runs.
     *
     * All three, since 2026-09-01. The three implementations are still separate
     * on purpose: each resolves a device from a bare token against its own table,
     * so one route set over one table could hand a Karate screen a Taekwondo
     * board. What is shared is the DOOR, not the fleet behind it — which is the
     * distinction the old sport => controller map in HallScreenRouter could not
     * express, and the reason a guard went missing every time one was copied.
     *
     * A sport absent from this list has no mat, and asking for one is a 404.
     */
    private const MATS = [
        'bjj' => HallScreenController::class,
        'karate' => KarateCourtDisplay::class,
        'taekwondo' => TaekwondoCourtDisplay::class,
    ];

    /**
     * The device model behind each fleet, so a claim can create one and a
     * pairing code can be resolved against every place one can live.
     *
     * Public because it is a MODEL, and models are a module's public face
     * (ModuleBoundaryTest). What stays private is the controller behind it.
     *
     * @var array<string, class-string>
     */
    private const DEVICES = [
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice::class,
        'karate' => \App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice::class,
    ];

    /**
     * The board address a newly adopted screen is sent to, by sport.
     *
     * A map rather than a conditional: the failure it would hide is a television
     * pointed at another sport's board, which only shows itself on competition
     * morning.
     *
     * @var array<string, string>
     */
    private const BOARD_ROUTES = [
        'bjj' => 'bjj-screen.board',
        'karate' => 'karate-court-display.board',
        'taekwondo' => 'court-display.board',
    ];

    /**
     * The SCORING TABLE's address, by sport.
     *
     * Separate from BOARD_ROUTES because they are opposite ends of the same mat:
     * a board is hung on a wall and reads, a console sits on a table and writes.
     * A map for the same reason as the boards' — the failure a conditional would
     * hide is an official handed another sport's table.
     *
     * Every one of them binds the event's UUID and re-checks
     * EventAccess::canScore on the way in, so this address is a convenience and
     * never a permission. Handing it to somebody who may not score gets them a
     * refusal, not a mat.
     *
     * @var array<string, string>
     */
    private const CONSOLE_ROUTES = [
        'bjj' => 'bjj-scoreboard.control',
        'karate' => 'karate-scoreboard.control',
        'taekwondo' => 'taekwondo-scoreboard.control',
    ];

    /**
     * Where an official opens this event's scoring table, or null if the sport
     * has no mat.
     *
     * This is the only way into the console from inside the product. Until it
     * existed the page could be reached two ways — by pairing a tablet to it, or
     * by an Open Mat / Sparring screen that built the URL itself — and neither
     * is available to an organiser sitting at a laptop running a tournament.
     * The console was, in effect, a page with no door.
     *
     * A mat may be named; when it is not, the console opens on the event's first
     * one, which is right for the common case of a single-mat event.
     */
    public static function consoleUrl(ClubEvent $event, ?string $court = null): ?string
    {
        $route = self::CONSOLE_ROUTES[(string) $event->sport] ?? null;

        if ($route === null) {
            return null;
        }

        return route($route, array_filter([
            'event' => $event->uuid,
            'mat' => $court,
        ]));
    }

    public function runs(?string $sport): bool
    {
        return $sport !== null && isset(self::MATS[$sport]);
    }

    /**
     * Every fleet, asked in full.
     *
     * PairingLog has to resolve a code against EVERY place a code can live
     * before it can honestly say "this code is nothing on this server", and that
     * answer is worthless if it silently skips a fleet somebody added later.
     *
     * @return array<string, class-string>
     */
    public static function fleets(): array
    {
        return self::DEVICES;
    }

    /** The device model for one sport's fleet, or null if it has none. */
    public static function deviceFor(?string $sport): ?string
    {
        return self::DEVICES[(string) $sport] ?? null;
    }

    /** The board route a screen on this sport is sent to. */
    public static function boardRoute(?string $sport): ?string
    {
        return self::BOARD_ROUTES[(string) $sport] ?? null;
    }

    /** The screens paired to this event's mats. */
    public function screens(Request $request, ClubEvent $event)
    {
        return $this->mat($event)->screens($request, $event);
    }

    /** Claim a waiting screen onto one of this event's mats. */
    public function pair(Request $request, ClubEvent $event)
    {
        return $this->mat($event)->pair($request, $event);
    }

    /**
     * Unclaim one.
     *
     * Named for what the console calls it. Unpairing is NOT revoking: the device
     * goes back to showing a fresh pairing code, because killing its token would
     * strand a screen nobody in the hall can re-enrol (CLAUDE.md → "Unattended
     * Devices Must Always Recover").
     */
    public function revokeScreen(Request $request, ClubEvent $event, int $device)
    {
        return $this->mat($event)->revokeScreen($request, $event, $device);
    }

    private function mat(ClubEvent $event): object
    {
        $owner = self::MATS[(string) $event->sport] ?? null;

        abort_unless($owner, 404);

        return app($owner);
    }
}
