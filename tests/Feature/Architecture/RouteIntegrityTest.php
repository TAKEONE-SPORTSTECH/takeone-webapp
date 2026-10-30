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
    /*
     * RECONCILED 2026-09-15, on the merge that brought every outstanding branch
     * onto main. The note above asked whoever committed the branch to confirm
     * the additions and set the constant once, with the reasons. Done — and the
     * gap turned out to be wider than 727, because the branches were merged in
     * between: the table stands at 749.
     *
     * What was actually verified, rather than waved through:
     *
     *   · NOTHING WAS REMOVED. The route-name list at 026015c2 (the last tree
     *     that still boots against today's vendor) is a strict subset of
     *     today's — no route lost a name, changed one, or disappeared in any of
     *     the module moves. That is the failure this tripwire exists to catch,
     *     and it did not happen.
     *   · Every route still resolves to a real callable, and no name is
     *     duplicated or unresolvable — the three tests either side of this one
     *     pass, so only the COUNTS had drifted.
     *
     * The nineteen added since 026015c2, all of them audited by name:
     *
     *   · SIX for the interface language (App\Translation, admin side) —
     *     `admin.languages{,.status,.strings,.translate,.update}` and
     *     `admin.ai.translation-primary`, the super-admin's screen for
     *     generating and reviewing a lang/ file.
     *   · SEVEN for content translation — `events.public.language.prepare` /
     *     `.status`, the visitor asking for a language and waiting for it, and
     *     `me.events.translations{,.offered,.update,.retranslate,.destroy}`,
     *     the organiser's review screen. (The earlier note named six of these;
     *     `.offered` is the seventh.)
     *   · ONE, `events.public.visitors` — the public poster's own read of who
     *     is looking at it.
     *   · FIVE for the league package — `me.events.league.data` plus its teams
     *     (store / destroy) and fixtures (generate / update). They live in
     *     app/Events/League/routes.php and are the whole of that package's
     *     surface, which is what a package owning its routes is supposed to
     *     look like.
     *
     * The fifty between 680 and 730 are the module migration, the BJJ package,
     * the scoreboard split and the mat camera panel, merged here from `BJJ` and
     * `development`. They cannot be enumerated commit-by-commit any more —
     * those trees no longer boot against the current vendor — so they are
     * recorded as a block rather than pretended to be individually reviewed.
     * The subset check above is what stands behind them.
     */
    /*
     * 750 since 2026-09-15: `me.events.bans` (GET me/events/{event}/bans), the
     * organiser's read of who is barred from their event.
     *
     * A door of its own rather than a payload on the console, deliberately: the
     * list had been riding in the roster partial's client-side search index on
     * every load, for a tab that page does not render — so a moderated member's
     * name reached every browser that opened the entry list and was drawn on
     * none of them. Now it is sent only to a request that asks, from somebody
     * `assertCanManage` has already admitted. One route added on purpose.
     */
    /*
     * 767 since 2026-09-22, +16, all of them named and all from the run-day
     * work that was sitting uncommitted on brackets-fix until it was
     * checkpointed in 89518117. Reconciled by diffing the route files across
     * that commit rather than by reading the number off the tree: 22 name(…)
     * lines were added, 4 of which rewrite an existing name
     * (events.divisions.members and the three events.verify.*), and two more
     * — events.bans and events.person.divisions — were already counted in the
     * notes below. 22 - 4 - 2 = 16.
     *
     *   me.events.rehearsal.begin / .end            a dry run of the day
     *   me.events.bracket.admit / .candidates       late entry into a cut draw
     *   me.events.bracket.third-place               the bronze switch
     *   me.events.danger-code                       the destructive-action gate
     *   me.events.run-order{,.data,.store,.reorder,.update,.destroy}   (6)
     *   me.events.order{,.page,.move,.auto}                            (4)
     *
     * ⚠️ Those last two groups are the SAME FEATURE twice — the two rival
     * running-order boards recorded in Documentation/MAP.md and on the
     * house-cleaning register. Ten of these sixteen routes serve one job, and
     * this tripwire is being moved to accept that rather than to bless it.
     * When the duplicate goes, this number drops and the note goes with it.
     *
     * 751 since 2026-09-16: `me.events.person.divisions`
     * (GET me/events/{event}/people/{uuid}/divisions) — every group in the
     * event as it stands for ONE athlete, with compatibility already judged.
     *
     * The mirror of `divisions.candidates`, which asks the same question from
     * the group's end. It exists because the participant card's organiser sheet
     * enrols a person across several groups at once, and the rule that decides
     * which ones fit (DivisionRange) must be answered in one place for both
     * screens. Bound by the athlete's uuid because the card that opens it lives
     * on the public entry list, where the public key is the only identifier
     * present. One route added on purpose.
     */
    /*
     * 769 since 2026-09-23: +2, the federation dashboard (federations.home,
     * federations.dashboard) in app/Federations/routes.php. The Clubs/Members
     * file moves the same day changed no route — the table was diffed before
     * and after, URI by URI, name, middleware and action.
     */
    /*
     * 937 since 2026-10-03 (+168), reconciled on branch major-change. Every one
     * of them was added by a COMMITTED feature since ff177fb1, none by the
     * uncommitted tree — a git diff of every module's routes file since ff177fb1 shows 209
     * route lines added and 27 removed across: federations (dashboard API,
     * invitations, staff, events), certificates (member + public verify), the
     * /business front door and chain views, sport championships, club ranks,
     * the AI routes, platform tools, and the /e/ → /me/events move of
     * 2026-09-27 (same names, new addresses). The closure count is unchanged.
     *
     * Also from this date the suite reads the route FILES, never
     * bootstrap/cache/routes-v7.php (phpunit.xml APP_ROUTES_CACHE): the cache
     * is whatever the last deploy built, not the code under test.
     */
    /*
     * 941 since 2026-10-03: +4, the club owners panel (App\Parties, PLAN.md
     * Phase 5) — admin.club.owners, .owners.propose, .owners.vote,
     * .owners.withdraw. All four answer 404 unless PARTIES_ENABLED.
     */
    /*
     * 942 since 2026-10-03: +1, me.events.entry-channels — how athletes enter
     * an event, the organiser's switch (PLAN.md Phase 7).
     *
     * 948 since 2026-10-04: +6, the new UI (Documentation/major-change/NEW-UI.md)
     * — me.new-ui, me.new-ui.on, me.new-ui.off, me.manage, me.ask,
     * me.ask.message. All six answer 404 unless NEW_UI is on (or, for the
     * preview three, preview).
     *
     * 954 since 2026-10-04: +6, "Register your club" at the event's own
     * addresses (ClubEntryController) — me.events.club-entry.{rules, club,
     * club-athletes, club-review, club-done, club-entries}. All six answer 404
     * unless EVENT_CLUB_ENTRY is on and the event's host has a ClubEntryDesk.
     *
     * 956 the same day: +2, the rules acceptance — events.club-entry.consent
     * and events.club-entry.consent-club (ClubEntryController, EntryConsent).
     *
     * 957 the same day: +1, events.share-card — the drawn picture a shared
     * event link unfurls into (ShareCardController, ShareCard).
     *
     * 958 the same day: +1, me.events.visibility — the organiser's Listed /
     * Hidden switch (PersonalEventController::setVisibility).
     *
     * 965 the same day: +7, events.club-entry.api.{clubs, code, verify, choose,
     * check-club, submit, withdraw} — "Register your club" on a club-hosted
     * event (ClubEntryApiController). 404 unless EVENT_CLUB_ENTRY_PLATFORM is on.
     * 966: +1 found at the same time, the AI providers' `/ai/models` route
     * (app/Ai/routes.php, changed separately on 2026-10-04).
     *
     * 970 the same day: +4, club payment proofs — events.club-entry.api.proof
     * (the club's upload) and me.events.club-payments{,.proof,.decide} (the
     * organiser's list, proof and decision; ClubPaymentsController).
     *
     * 983 on 2026-10-07: +13 — me.events.bracket.pdf (the draw as an A4 PDF,
     * BracketPdfController); me.events.draw-pin{,.update,.destroy} (the
     * organiser's tablet PIN, DrawPinController); and the nine tablet.draw*
     * doors (the draw on a scoring tablet, TabletDrawController). The PDF is
     * 404 unless EVENT_BRACKET_PDF, the other twelve unless EVENT_TABLET_DRAW.
     * 985 the same day: +2, tablet.draw.outcome and tablet.draw.entrant — the
     * bout tools (winner/score, a name) on a PIN-unlocked tablet.
     * 986 the same day: +1, me.events.run-order.break — the event's own break
     * edited from the running order (PersonalEventController::updateEventBreak).
     * 1000 on 2026-10-08: +14 — the scoring consoles' Corners tab
     * (App\Scoreboard\Corners): {karate,taekwondo,bjj}-scoreboard .corners and
     * .corner-look, each through the signed-in and the paired-tablet door (12),
     * plus karate-scoreboard.console-state and .token-console-state — the
     * Karate console re-reading itself when the running order moves.
     * 1002 the same day: +2 — the super-admin's "Remove club"
     * (admin.platform.clubs.removal + .removal.store, ClubRemovalController).
     * 1005 on 2026-10-08: +3 — the tablet draw's bout numbers
     * (tablet.draw.number, .renumber, .number.release, TabletDrawController).
     * 1030 on 2026-10-12: +25 — the event gallery photos, officials' badges,
     * theme colour, corners and exhibition bouts (49327f77).
     * 1031 on 2026-10-13: +1 — the participants PDF
     * 1042 on 2026-10-13: +11 — Budget & profit (me.events.budget*, EventBudgetController)
     * 1043 on 2026-10-13: +1 — the budget advisor (me.events.budget.advisor)
     * 1044 on 2026-10-13: +1 — the budget's division simulator (me.events.budget.divisions)
     * 1046 on 2026-10-13: +2 — the advisor's memory (me.events.budget.advisor.history / .reset)
     * 1048 on 2026-10-13: +2 — editing an athlete from the federation's Athletes tab
     *   (federations.api.regs.athlete / .athlete.update, FederationAthleteEditor)
     * (me.events.participants.pdf, ParticipantsPdfController).
     * 1050 on 2026-10-14: +2 — dragging a competitor into another division's draw
     *   (me.events.bracket.transfer, tablet.draw.transfer; Transfer)
     * 1051 on 2026-10-14: +1 — a running-order group's method (me.events.run-order.session)
     * 1052 on 2026-10-14: +1 — dragging an item into or out of a group (me.events.run-order.move)
     * 1056 on 2026-10-14: +4 — the tablet's draw screen at the organiser's desk
     *   (me.events.draw-desk, .number, .renumber, .number.release; DrawDeskController)
     * 1057 on 2026-10-14: +1 — Shuffle on a scoring tablet (tablet.draw.shuffle; FairDraw)
     * 1058 on 2026-10-14: +1 — "Renumber bouts" (me.events.order.renumber; BoutNumbering)
     * 1059 on 2026-10-14: +1 — "Renumber bouts" on a scoring tablet (tablet.draw.renumber-all)
     * 1060 on 2026-10-14: +1 — the divisions editor's data as JSON (me.events.divisions.data; the federation Divisions tab)
     * 1061 on 2026-10-14: +1 — the event's blank bilingual registration form PDF (me.events.registration-form.pdf)
     * 1062 on 2026-10-14: +1 — the same form filled in for one entrant (me.events.entrant.registration-form)
     * 1065 on 2026-10-15: +3 — merging small divisions (me.events.divisions.merge{,.suggest,.check}; DivisionMerge)
     * 1066 on 2026-10-15: +1 — the merging handbook PDF (me.events.divisions.merge.handbook)
     * 1067 on 2026-10-15: +1 — entrants in no division, with where each fits (me.events.divisions.unplaced)
     * 1069 on 2026-10-15: +2 — delete every division / auto-place (me.events.divisions.delete-all, .auto-place; DivisionReset)
     * 1070 on 2026-10-15: +1 — the weight tables auto-place can follow (me.events.divisions.auto-place.systems)
     * 1071 on 2026-10-15: +1 — the Participants tab's athlete editor frame (federations.athlete-edit)
     * 1074 on 2026-10-15: +3 — the Officials tab's editor (federations.official-edit, api.officials.one, api.officials.person)
     * 1075 on 2026-10-15: +1 — take a whole club off an event (federations.api.regs.withdraw-club)
     * 1076 on 2026-10-15: +1 — the officials as a PDF (me.events.officials.pdf)
     * 1078 on 2026-10-15: +2 — the Clubs tab's club editor (federations.club-edit, federations.api.clubs.logo)
     * 1081 on 2026-10-15: +3 — divisions from a spreadsheet (me.events.divisions.import{,.preview,.template}; DivisionImport)
     * 1082 on 2026-10-16: +1 — auto-schedule the day (me.events.run-order.auto)
     * 1083 on 2026-10-16: +1 — when the day starts (me.events.run-order.start)
     * 1085 on 2026-10-16: +2 — the desk's division editor: a candidate onto a board, and one division per person (me.events.bracket.pull, me.events.bracket.one-division)
     * 1086 on 2026-10-16: +1 — which flag an athlete flies, club country or nationality (me.events.flag-source)
     */
    private const EXPECTED_ROUTE_COUNT = 1086;

    /**
     * Routes handled by a Closure rather than a controller action.
     *
     * 23 since 2026-10-30: +1, DELETE /mcp — laravel/mcp v0.3.26 (up from
     * v0.3.11) registers it beside POST /mcp, answering 405 with Allow: POST.
     */
    private const EXPECTED_CLOSURE_COUNT = 23;

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
        // 738 with `me.events.bans`, the same day — see the block above
        // EXPECTED_ROUTE_COUNT for why that list is read through a door of its
        // own rather than handed to every console that renders.
        // 668 → 737 on 2026-09-15, reconciled with EXPECTED_ROUTE_COUNT in the
        // same commit and for the same reasons — see the block above it. Twelve
        // routes remain unnamed, the same twelve as at 026015c2, so nothing
        // lost a name in the module moves.
        // 739 with `me.events.person.divisions` on 2026-09-16 — the participant
        // card's enrolment sheet; see the block above EXPECTED_ROUTE_COUNT.
        // 755 on 2026-09-22: the same +16 as EXPECTED_ROUTE_COUNT, since every
        // route the run-day work added carries a name. Twelve routes remain
        // unnamed, the same twelve as before, so nothing lost a name in the
        // model moves either.
        // 757 on 2026-09-23: the two federation routes, both named.
        // 924 on 2026-10-03: the +168 above, all named except ONE — `busniess`,
        // the misspelling redirect the /business front door added (50897a22).
        // Thirteen unnamed now: mcp ×3, busniess, profile, family, members,
        // family/dashboard, POST login, POST register, api/user, up, /.
        // 928 on 2026-10-03: the four owners-panel routes, all named.
        // 929 on 2026-10-03: me.events.entry-channels.
        // 935 on 2026-10-04: the new UI's six routes (see EXPECTED_ROUTE_COUNT).
        // 941 the same day: the six club-entry step addresses; 943 with the two consent doors; 944 with the share card; 945 with visibility; 952 with the club-entry doors; 953 with /ai/models; 957 with club payments.
        // 970 on 2026-10-07: +13, the bracket PDF, the tablet PIN and the tablet draw doors; 972 with the tablet's bout tools; 973 with the running order's break editor.
        // 987 on 2026-10-08: +14, the scoring consoles' Corners tab (corners + corner-look × 3 sports × 2 doors) and Karate's console-state doors.
        // 989 the same day: +2, admin.platform.clubs.removal + .removal.store.
        // 992 on 2026-10-08: +3, tablet.draw.number / .renumber / .number.release.
        // 1017 on 2026-10-12: +25, the routes of 49327f77 (see EXPECTED_ROUTE_COUNT).
        // 1018 on 2026-10-13: +1, the participants PDF.
        // 1029 on 2026-10-13: +11, Budget & profit.
        // 1030 on 2026-10-13: +1, the budget advisor.
        // 1031 on 2026-10-13: +1, the division simulator.
        // 1033 on 2026-10-13: +2, the advisor's memory.
        // 1035 on 2026-10-13: +2, the federation's athlete edit (regs.athlete / .athlete.update).
        // 1037 on 2026-10-14: +2, the split view's transfer doors (member + tablet).
        // 1038 on 2026-10-14: +1, me.events.run-order.session.
        // 1039 on 2026-10-14: +1, me.events.run-order.move.
        // 1043 on 2026-10-14: +4, me.events.draw-desk (page + three bout-number doors).
        // 1044 on 2026-10-14: +1, tablet.draw.shuffle.
        // 1045 on 2026-10-14: +1, me.events.order.renumber.
        // 1046 on 2026-10-14: +1, tablet.draw.renumber-all.
        // 1047 on 2026-10-14: +1, me.events.divisions.data.
        // 1048 on 2026-10-14: +1, me.events.registration-form.pdf.
        // 1049 on 2026-10-14: +1, me.events.entrant.registration-form.
        // 1052 on 2026-10-15: +3, me.events.divisions.merge{,.suggest,.check}.
        // 1053 on 2026-10-15: +1, me.events.divisions.merge.handbook.
        // 1054 on 2026-10-15: +1, me.events.divisions.unplaced.
        // 1056 on 2026-10-15: +2, me.events.divisions.delete-all / auto-place.
        // 1057 on 2026-10-15: +1, me.events.divisions.auto-place.systems.
        // 1058 on 2026-10-15: +1, federations.athlete-edit.
        // 1061 on 2026-10-15: +3, federations.official-edit / api.officials.one / api.officials.person.
        // 1062 on 2026-10-15: +1, federations.api.regs.withdraw-club.
        // 1063 on 2026-10-15: +1, me.events.officials.pdf.
        // 1065 on 2026-10-15: +2, federations.club-edit / federations.api.clubs.logo.
        // 1068 on 2026-10-15: +3, me.events.divisions.import / .import.preview / .import.template.
        // 1069 on 2026-10-16: +1, me.events.run-order.auto.
        // 1070 on 2026-10-16: +1, me.events.run-order.start.
        // 1072 on 2026-10-16: +2, me.events.bracket.pull / me.events.bracket.one-division.
        // 1073 on 2026-10-16: +1, me.events.flag-source.
        $this->assertCount(1073, $named, 'Named-route count changed — tripwire, see class docblock.');
    }
}
