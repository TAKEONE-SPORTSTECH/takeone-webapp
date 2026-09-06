<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventOfficial;
use App\Members\Models\User;

/**
 * Who may see an event, and who may run it.
 *
 * One place, because this rule now has two callers — the web screens and the
 * MCP server — and an authorization rule that exists twice is an authorization
 * rule that will eventually disagree with itself. The MCP must never expose
 * more than the acting user could reach in the UI, so both ask this.
 */
class EventAccess
{
    /** Visible to anyone the event's scope reaches (host-club members + wider). */
    public function visible(ClubEvent $event, User $user): bool
    {
        if ($event->is_archived) {
            return false;
        }

        return $this->eligible($event, $user) || $this->canManage($event, $user);
    }

    /**
     * Who RUNS this event — and may therefore do anything to it.
     *
     * Everything else in this class builds on this one answer: the draw, the
     * weigh-in, the payments, the scoring table, the console, the financials,
     * the delete button. So it is deliberately a short list, and every name on
     * it is somebody the event already belongs to:
     *
     *   · the account that CREATED it;
     *   · whoever owns or administers the club HOSTING it — a club runs its own
     *     competitions, and an owner who could not open their own club's event
     *     console had to be appointed to it by somebody else first (which is
     *     exactly what happened, and why this widened on 2026-09-03);
     *   · anyone appointed to this event as its ORGANISER, which is the
     *     event's own way of saying "this person runs it too";
     *   · platform staff, as the standing override.
     *
     * ⚠️ SCOPED TO ONE EVENT, always. This grants nothing anywhere else: it
     * takes the event as its first argument and every caller asks it per event,
     * so an organiser has a super-admin's reach over THEIR competition and a
     * stranger's everywhere else. Owning a club does not grant anything on
     * another club's event, and being appointed to one event grants nothing on
     * the next.
     *
     * Memoised per instance: a roster page asks this once per row, and the
     * answer cannot change inside a request.
     */
    public function canManage(ClubEvent $event, User $user): bool
    {
        $key = $event->id.':'.$user->id;

        return $this->manages[$key] ??= $this->resolveCanManage($event, $user);
    }

    /** @var array<string, bool> */
    private array $manages = [];

    private function resolveCanManage(ClubEvent $event, User $user): bool
    {
        if ($event->created_by === $user->id || $user->isSuperAdmin()) {
            return true;
        }

        $tenantId = (int) ($event->tenant_id ?? 0);

        if ($tenantId !== 0) {
            // The host club's owner. Owning a club and being a MEMBER of it are
            // different things (a club owner is often neither), so this asks the
            // tenant directly rather than going through memberships.
            $owns = \App\Clubs\Models\Tenant::whereKey($tenantId)
                ->where('owner_user_id', $user->id)
                ->exists();

            if ($owns || $user->isClubAdmin($tenantId)) {
                return true;
            }
        }

        // Appointed to THIS event, by name, as the person running it.
        return $this->isOfficial($event, $user, 'organiser');
    }

    /**
     * Appointed to officiate THIS event — the jury.
     *
     * Deliberately not folded into canManage(): that gates editing, deleting,
     * results and financials, and a jury has no business there. It is checked
     * on its own by canArrangeDraw().
     */
    public function isOfficial(ClubEvent $event, User $user, string $role = 'jury'): bool
    {
        return $event->officials()
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->exists();
    }

    /**
     * Who may hand-arrange a draw: the organiser who created the event, the
     * jury appointed to it, and platform staff.
     *
     * This answers "who", never "when" — the event-started gate lives in the
     * package's availableActions() and in Arrangement itself, so a draw is
     * final from the first bout no matter who is asking.
     */
    public function canArrange(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user) || $this->isOfficial($event, $user);
    }

    /**
     * May this person read the draw yet?
     *
     * TWO questions in one answer, and they are asked in this order on purpose:
     *
     *   1. Is the draw out?  — the organiser's setting plus the calendar,
     *      answered by ClubEvent::drawRevealed(). Nothing to do with the reader.
     *   2. Is the reader one of the people building it? — the organiser and
     *      every official appointed to this event read the draw at every
     *      setting. Concealing a bracket from the jury that has to arrange it
     *      would make the setting unusable on the day it matters most.
     *
     * A concealed draw is withheld, never faked: the board says when it opens
     * rather than pretending there is no draw, because an athlete who cannot
     * tell "not published yet" from "nobody has entered" will ask the organiser,
     * and that phone call is the thing this setting exists to prevent.
     *
     * ⚠️ This governs DISCLOSURE only. It is not a lock on the draw — arranging
     * is gated by canArrange() and by the package's own started-event rule, and
     * neither is loosened or tightened by anything here.
     */
    public function drawVisible(ClubEvent $event, ?User $user = null): bool
    {
        if ($event->drawRevealed()) {
            return true;
        }

        // Nobody signed in — the public event page. A concealed draw is
        // concealed from strangers at every setting.
        if ($user === null) {
            return false;
        }

        return $this->canManage($event, $user)
            || $event->officials()->where('user_id', $user->id)->exists();
    }

    /**
     * Record and verify official weights.
     *
     * The organiser is included because someone has to be able to run a
     * weigh-in when the appointed official does not turn up — but the row still
     * records WHO signed it off, so an unverified weight can never reach the
     * final draw just because nobody was appointed.
     */
    public function canVerifyWeighIn(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_WEIGH_IN);
    }

    /** Check proof of payment against the club account and approve it. */
    public function canVerifyPayments(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_PAYMENTS);
    }

    /**
     * Run the table at a mat: load a bout onto the hall screens, score it, and
     * work the clock.
     *
     * The organiser who created the event, anyone appointed to officiate it in
     * any capacity, and platform staff. Deliberately wider than canManage() and
     * narrower than "signed in": scoring is done by whoever is sitting at the
     * table, which at a real competition is a jury member rather than the person
     * who typed the event in — but it drives what a room full of people sees, so
     * it stays inside the appointed list.
     *
     * It is NOT a result: a bout's running score is scaffolding, and the outcome
     * still lands through recordOutcome(), which has its own authorisation.
     */
    public function canScore(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_JURY)
            || $this->isOfficial($event, $user, EventOfficial::ROLE_ORGANISER);
    }

    /** Any officiating job at all — used to decide who sees the console. */
    public function canOfficiate(ClubEvent $event, User $user): bool
    {
        return $this->canVerifyWeighIn($event, $user) || $this->canVerifyPayments($event, $user);
    }

    /**
     * May this person put an athlete into THIS event who is not on the platform?
     *
     * A second, EVENT-scoped authority beside the club-scoped one in
     * EntryService::administeredClubIds(). The two answer different questions
     * and both are legitimate:
     *
     *   · the CLUB's — "I enter athletes for the club I run", which is a coach
     *     with `enter-athletes` submitting their team, wherever the competition
     *     is.
     *   · this one   — "I am running THIS competition", which is the organiser
     *     and anyone they appointed to it.
     *
     * The second was missing, and its absence showed at the door: a jury member
     * or a weigh-in official standing at the desk on the morning, handed a
     * walk-in with no account, could not enter them unless they also happened to
     * be an admin of some club. That is the one moment the job exists for.
     *
     * Deliberately EVERY appointment, not just the jury. Who stands at the entry
     * desk is a question about how a hall is staffed, not about privilege — the
     * weigh-in official is usually the person holding the paper list.
     *
     * Scoped to one event and nothing else: an appointment says nothing about
     * the next competition, and this grants no power over the athlete's account
     * afterwards.
     */
    public function canEnterAthletes(ClubEvent $event, User $user): bool
    {
        return $this->canManage($event, $user)
            || $event->officials()->where('user_id', $user->id)->exists();
    }

    public function eligible(ClubEvent $event, User $user): bool
    {
        if ($user->memberClubs()->whereKey($event->tenant_id)->exists()) {
            return true;
        }

        return match ($event->scope ?? 'internal') {
            'inter_club', 'worldwide' => true,
            // regional currently mirrors nationwide until a region taxonomy exists.
            'nationwide', 'regional' => $this->shareCountry($event, $user),
            default => false, // internal
        };
    }

    /** True when the member belongs to a club in the host club's country. */
    private function shareCountry(ClubEvent $event, User $user): bool
    {
        $hostCountry = $event->tenant?->country ?? $event->tenant()->value('country');

        return $hostCountry
            && $user->memberClubs()->where('tenants.country', $hostCountry)->exists();
    }
}
