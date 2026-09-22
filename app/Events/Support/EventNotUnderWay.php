<?php

namespace App\Events\Support;

use RuntimeException;

/**
 * A result was offered against a competition nobody has started.
 *
 * A TYPE rather than a plain RuntimeException because the console must tell
 * this refusal from every other one. Every other refusal ("that bout is over",
 * "no bout is loaded") is the official's cue to do something different; this
 * one is a cue to do the SAME thing after one more tap, so the console turns it
 * into a "Start the day?" confirmation rather than a dead end.
 *
 * That distinction is the whole design. Refusing with no way forward is what
 * stopped a championship on 2026-09-18 — two bouts fought, neither recordable,
 * the running order stuck behind them, and the button that would have cleared
 * it on an organiser's screen elsewhere in the hall.
 */
class EventNotUnderWay extends RuntimeException
{
}
