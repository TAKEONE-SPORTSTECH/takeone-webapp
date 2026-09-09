<?php

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Architecture characterization: the shape of the route table.
 *
 * This test does NOT say what the routing SHOULD be. It records what it IS
 * today, so that a refactor phase which moves controllers between modules,
 * renames a namespace or re-registers a package's routes cannot silently add,
 * drop or orphan a route. Every number here is a TRIPWIRE: if it fires, the
 * question is not "how do I make the test green" but "did I mean to change the
 * number?". Update the constant deliberately, in the same commit as the change
 * that moved it, with a note in the commit message.
 */
class RouteIntegrityTest extends TestCase
{
    /**
     * Total registered routes.
     *
     * Measured 2026-08-31 on branch `development` at 650 (`php artisan
     * route:list --json | jq length` agrees with the in-test count, so the CLI
     * and the test boot the same table). It stayed at 650 through the Shop,
     * Clubs, Members, Challenges and Trainers module migrations — moving a
     * controller into a module must never change this number, which is the
     * whole reason the tripwire exists.
     *
     * 651 since 2026-09-01: `openmat.sport` (GET /openmat/{sport}), the direct
     * per-sport door onto an open mat. One route added on purpose.
     *
     * 649 since 2026-09-01: `me.push-tokens.store` / `me.push-tokens.destroy`
     * removed with Firebase. Native push is delivered over MQTT by the broker
     * the platform already runs, so there is no per-device token to register.
     *
     * 655 since 2026-09-01: the claim link (Documentation/EVENTS-PUBLIC-ENTRY.md,
     * Phase A). Two OPEN routes — `entry.claim` / `entry.claim.store`, the
     * athlete's side, reachable with no account — and four behind /me for the
     * coach: `me.events.entries.unnamed`, `me.events.entry-links`, and that
     * link's revoke / relink. Six added on purpose.
     *
     * 657 since 2026-09-01: the public event page (same document, Phase B) —
     * `events.public`, the read-only poster anybody may open, and
     * `me.events.public.toggle`, the organiser's switch for it. Two on purpose.
     *
     * 663 since 2026-09-01: public enrolment (same document, Phase C). Two OPEN
     * — `events.public.enter` and `events.public.enter.store`, the only place a
     * wholly unauthenticated stranger creates an account — and four behind /me
     * for the organiser: `me.events.public.auto-accept`,
     * `me.events.public-entries` and that queue's accept / decline. Six on
     * purpose.
     *
     * 665 since 2026-09-01: the shared link wears the EVENT's identity, not the
     * platform's — `events.public.manifest` and `events.public.icon`, the
     * per-event web-app manifest and its generated icon, so a competition added
     * to a home screen is that competition's app. Two on purpose.
     *
     * 670 since 2026-09-02. Five, and they arrived from two places — recorded
     * separately because a tripwire that lumps them together teaches nothing:
     *
     *   · THREE from the module / BJJ-package work already in the tree when the
     *     count was next checked. Not audited here; the number had drifted to
     *     668 before the two below were written.
     *   · TWO for the scoring console's own re-read —
     *     `bjj-scoreboard.console-state` and its token twin
     *     `bjj-scoreboard.token-console-state`. Both READS, authorised exactly
     *     as the command endpoint beside them. They exist because a console
     *     draws three things (state, log, queue) and so cannot patch itself from
     *     the single board payload a wall screen is sent: when the socket says
     *     the mat moved, it re-reads its own. See ScoreboardController.
     *
     * 677 since 2026-09-02: building a bracket BY HAND. Five, all under
     * `me.events.divisions.*` — create / update / delete a group, read who could
     * go in it, and put people in and out. Split by what the act is: the first
     * three need canManage, the last two only canArrange, because filling a
     * group is the same act as moving people around a draw. Every one scopes the
     * division to the event uuid in the path.
     */
    /*
     * 680 since 2026-09-02: `locale.set` (PUT /locale) — switching the UI
     * language without an account. `me.locale.update` already existed but sits
     * behind auth + verified + two-factor, and the first place a visitor is
     * offered a language is the PUBLIC event cover, before any of that. Same
     * controller, one route added on purpose.
     */
    /*
     * ⚠️ 2026-09-08 — this tripwire is ALREADY FIRING, and not only for the
     * work that wrote this note.
     *
     * The table stands at 727 against the 680 recorded here. Six of those are
     * the content-translation module (App\Translation), added deliberately and
     * named below; the other forty-one pre-date it and belong to work still
     * uncommitted in this tree. The number is deliberately NOT bumped to 727
     * here, because doing so would bless forty-one routes this change never
     * reviewed — which is the exact thing the tripwire exists to prevent.
     *
     * Whoever commits this branch reconciles it: confirm all the additions,
     * then set the constant once, with the reasons.
     *
     * The six from App\Translation:
     *   events.public.language.prepare / .status   the visitor picking a language
     *   me.events.translations{,.update,.retranslate,.destroy}
     *                                             the organiser's review screen
     */
    private const EXPECTED_ROUTE_COUNT = 680;   // +4: events.public.draw.data, events.public.section, events.public.enter.mine, events.public.enter.clubs; +5: me.events.divisions.*; +1: locale.set

    /** Routes handled by a Closure rather than a controller action. */
    private const EXPECTED_CLOSURE_COUNT = 22;

    /**
     * FIXED 2026-08-31: the list is now empty, and must stay empty.
     *
     * It used to hold `admin.`, shared by the three `Admin\ClubApiController`
     * endpoints that sat inside the `->name('admin.')` group without a name of
     * their own, so the group prefix became the whole name and Laravel's lookup
     * kept only the last registration. They are now named `admin.api.users`,
     * `admin.api.club` and `admin.api.clubs.check-slug` — URI, verb, middleware
     * and controller are untouched, and nothing called `route('admin.')`, so
     * naming them broke no caller (all three call sites fetch the literal URI).
     *
     * Anything appearing here again is a NEW collision: name the route, do not
     * extend this list.
     *
     * @var list<string>
     */
    private const KNOWN_DUPLICATE_NAMES = [];

    /**
     * The dangling-action list — now empty, and it must stay that way.
     *
     * It held `admin.storage.store` and `admin.storage.update` for as long as
     * `Admin\MediaVaultController` lacked those two methods, which made attaching
     * a media vault from the storage screen a live 500. Both are implemented
     * (2026-08-31), so the list is empty and any entry appearing here again is a
     * NEW break: write the method, do not extend this list.
     *
     * @var list<string>
     */
    private const KNOWN_BROKEN_ACTIONS = [];

    /** @return list<RoutingRoute> */
    private function routes(): array
    {
        return array_values(Route::getRoutes()->getRoutes());
    }

    public function test_the_route_table_is_exactly_the_size_it_was(): void
    {
        $this->assertCount(
            self::EXPECTED_ROUTE_COUNT,
            $this->routes(),
            'The number of registered routes changed. This is a tripwire, not a bug: '
            .'confirm the added/removed routes were intended, then update '
            .'EXPECTED_ROUTE_COUNT in the same commit.'
        );
    }

    public function test_the_number_of_closure_routes_is_unchanged(): void
    {
        $closures = array_filter(
            $this->routes(),
            fn (RoutingRoute $r) => $r->getActionName() === 'Closure'
        );

        $this->assertCount(
            self::EXPECTED_CLOSURE_COUNT,
            $closures,
            "Closure route count changed.\n".implode(
                "\n",
                array_map(fn (RoutingRoute $r) => $r->methods()[0].' /'.ltrim($r->uri(), '/'), $closures)
            )."\n\nClosure routes cannot be route:cache'd and have no class to move during a "
            .'module migration — keep an eye on the number.'
        );
    }

    public function test_route_names_are_unique_apart_from_the_known_collision(): void
    {
        $names = [];

        foreach ($this->routes() as $route) {
            if ($route->getName() !== null) {
                $names[] = $route->getName();
            }
        }

        $duplicates = array_values(array_keys(array_filter(
            array_count_values($names),
            fn (int $count) => $count > 1
        )));

        sort($duplicates);
        $known = self::KNOWN_DUPLICATE_NAMES;
        sort($known);

        $this->assertSame(
            $known,
            $duplicates,
            "Duplicate route names shadow each other — only the last registration is\n"
            ."reachable through route(). See KNOWN_DUPLICATE_NAMES for the one\n"
            .'pre-existing case; anything else here is new and should be named properly.'
        );
    }

    public function test_every_controller_route_points_at_a_real_callable(): void
    {
        $broken = [];
        $checked = 0;

        foreach ($this->routes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure') {
                continue;
            }

            $checked++;

            if (in_array($action, self::KNOWN_BROKEN_ACTIONS, true)) {
                // Asserted separately below, so the count stays honest.
                continue;
            }

            [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');

            $where = $route->methods()[0].' /'.ltrim($route->uri(), '/');

            if (! class_exists($class)) {
                $broken[] = "{$where} → class {$class} does not exist";

                continue;
            }

            // A controller may answer through __call/__callStatic magic, so
            // is_callable is the honest question, not method_exists.
            if (! is_callable([$class, $method]) && ! method_exists($class, $method)) {
                $broken[] = "{$where} → {$class}::{$method}() is not callable";
            }
        }

        $this->assertSame([], $broken, implode("\n", array_merge(
            ['Routes point at a controller action that cannot be resolved:', ''],
            $broken,
        )));

        $this->assertSame(
            self::EXPECTED_ROUTE_COUNT - self::EXPECTED_CLOSURE_COUNT,
            $checked,
            'Controller-backed route count drifted from total minus closures.'
        );
    }

    /**
     * Pins the known-broken list so it can only grow by someone deliberately
     * deciding to record a break — and so a NEW dangling action fails the test
     * above instead of being waved through. The list is empty today.
     */
    public function test_the_known_dangling_controller_actions_are_still_exactly_those_recorded(): void
    {
        $dangling = [];

        foreach ($this->routes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure') {
                continue;
            }

            [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');

            if (class_exists($class) && ! method_exists($class, $method)) {
                $dangling[] = $action;
            }
        }

        $dangling = array_values(array_unique($dangling));
        sort($dangling);

        $known = self::KNOWN_BROKEN_ACTIONS;
        sort($known);

        $this->assertSame($known, $dangling, 'The set of route→missing-method pairs changed.');
    }

    public function test_every_named_route_is_resolvable_by_name(): void
    {
        $unresolvable = [];

        foreach ($this->routes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            if (! Route::has($name)) {
                $unresolvable[] = $name.' ('.$route->uri().')';
            }
        }

        $this->assertSame([], $unresolvable, implode("\n", array_merge(
            ['Named routes that the name lookup cannot find:', ''],
            $unresolvable,
        )));
    }

    /**
     * A route with a name is the only kind another view can link to safely
     * (Navigation Integrity). Record how many there are so a migration that
     * quietly drops a `->name()` is visible.
     */
    public function test_the_named_route_count_is_unchanged(): void
    {
        $named = array_filter(
            $this->routes(),
            fn (RoutingRoute $r) => $r->getName() !== null
        );

        // 639 with `openmat.sport`, then 637: the two push-token routes went
        // with Firebase. 643 with the six claim-link routes (Phase A of
        // EVENTS-PUBLIC-ENTRY), 645 with the public page and its switch
        // (Phase B), 651 with public enrolment and the organiser's review
        // queue (Phase C), 653 with the per-event manifest and icon that make
        // the link its own app. 658 on 2026-09-02: three from the module / BJJ
        // work already in the tree, plus the scoring console's two re-read
        // routes — see the note on EXPECTED_ROUTE_COUNT. 659 with
        // `events.public.draw.data`, the public page's read-only draw feed, and
        // 660 with `events.public.section` — one route for the poster's four
        // doors (draw, officials, gallery, participants). Every change
        // deliberate.
        $this->assertCount(668, $named, 'Named-route count changed — tripwire, see class docblock.');
    }
}
