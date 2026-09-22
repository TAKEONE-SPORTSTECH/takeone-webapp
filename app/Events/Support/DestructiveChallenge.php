<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use Illuminate\Support\Facades\Cache;

/**
 * The code an organiser has to type out before a competition can be called off
 * or erased.
 *
 * ── Why a typed code, and not another "are you sure?" ──────────────────────
 *
 * Cancel and Delete sat in the console as two ordinary buttons behind one
 * confirm dialog, which is the shape of every other dialog on the platform and
 * is dismissed by reflex. What is behind them is not ordinary: Delete takes the
 * event, its entries, its divisions, its draw and its results with it, on an
 * event that may have a hall full of people in it.
 *
 * A code that has to be READ and then TYPED cannot be answered by muscle memory.
 * It is the same instrument as the platform's own type-RESET danger zone, and it
 * is the deliberate-action gate the two most destructive buttons in the product
 * did not have.
 *
 * ── Why the server issues it ───────────────────────────────────────────────
 *
 * A code invented in the browser and checked in the browser protects nobody: the
 * DELETE still succeeds on its own, so anything that can reach the endpoint — a
 * replayed request, a mistyped script, a second tab, a tap that fires twice —
 * deletes the event exactly as before, and the gate is decoration (CLAUDE.md →
 * "never trust client-side claims"). So the code is MADE here, kept here, and
 * checked here. The request that does the destroying carries it or it is
 * refused.
 *
 * It is not a secret and is not treated as one — the person who must type it is
 * shown it. What it guarantees is that the destructive request was preceded, in
 * the last few minutes, by a human being who asked for a code and read it back.
 *
 * ── The rules it enforces ──────────────────────────────────────────────────
 *
 *  · Bound to the ACTION. A code issued for Cancel cannot delete.
 *  · Bound to the EVENT and to the PERSON. One organiser's code is not another's,
 *    and a code for last week's event is nothing here.
 *  · Single use. It is consumed the moment it works, so the same request replayed
 *    a second time is refused like any stranger.
 *  · Short-lived — five minutes. Long enough to read and type, short enough that
 *    a console left open on a table overnight is not still armed in the morning.
 *  · Ten wrong answers and it is gone. Not a serious guessing defence (the route
 *    is throttled and the reader is looking at the code), but a code that has
 *    been guessed at ten times is being used by somebody who is not reading it.
 */
class DestructiveChallenge
{
    /** What may be gated. A verb this does not know is refused, never allowed. */
    public const ACTIONS = ['cancel', 'delete'];

    /** Long enough to read a code and type it; short enough not to sit armed. */
    private const TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 10;

    /**
     * Deliberately not the whole alphabet: O/0 and I/1/l are the same shape in
     * most typefaces, and a person who mistypes a code they read correctly
     * learns that the gate is broken rather than that they are being careful.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 6;

    /**
     * Mint a code for this person, this event and this action.
     *
     * Any code already outstanding is replaced: asking again means the first one
     * was lost or ignored, and two live codes for one button is a state nobody
     * needs to reason about.
     *
     * @return array{code: string, expires_in: int}
     */
    public function issue(ClubEvent $event, int $actorId, string $action): array
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            // random_int, not rand(): this is a gate, and the cost of a
            // cryptographic source for six characters is nothing.
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        Cache::put($this->key($event, $actorId, $action), [
            'code' => $code,
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return ['code' => $code, 'expires_in' => self::TTL_SECONDS];
    }

    /**
     * Is this the code we issued? Consumed if so.
     *
     * Returns false for everything else — expired, never issued, wrong action,
     * wrong person, too many tries. ONE answer for all of them, because the
     * caller turns it into one sentence: telling somebody which way they are
     * wrong helps nobody who is reading the code off their own screen.
     */
    public function verify(ClubEvent $event, int $actorId, string $action, ?string $code): bool
    {
        $key = $this->key($event, $actorId, $action);
        $held = Cache::get($key);

        if (! is_array($held) || ! is_string($code) || $code === '') {
            return false;
        }

        // Typed by a human, so the case they used is not the question. The
        // comparison itself stays constant-time out of habit rather than need.
        if (hash_equals((string) $held['code'], strtoupper(trim($code)))) {
            Cache::forget($key);

            return true;
        }

        $attempts = (int) ($held['attempts'] ?? 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        // The remaining TTL is deliberately not extended by a wrong answer.
        Cache::put($key, ['code' => $held['code'], 'attempts' => $attempts], self::TTL_SECONDS);

        return false;
    }

    /** Throw the code away — used when a gated action is abandoned. */
    public function discard(ClubEvent $event, int $actorId, string $action): void
    {
        Cache::forget($this->key($event, $actorId, $action));
    }

    private function key(ClubEvent $event, int $actorId, string $action): string
    {
        return 'event.danger.'.$event->id.'.'.$actorId.'.'.$action;
    }
}
