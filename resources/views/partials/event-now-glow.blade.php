{{--
    The pulse that says "this is where the event is RIGHT NOW".

    Shared by the mobile and desktop run-of-show cards, so the two cannot drift
    (CLAUDE.md → Shared Stays Shared). One file, one definition, included by
    both under `@once`.

    ⚠️ A plain inline <style>, NOT `@push('styles')` and NOT a new class in
    app.css:

      · `@push('styles')` is dropped whenever the including view renders after
        the layout has already printed `@stack('styles')`, which is how the
        visitor counter's CSS went missing earlier today. An inline block
        cannot be mistimed.
      · app.css is COMPILED. A class added there does not exist in
        public/build until somebody runs a build, and the public event surface
        is the one place on the platform where that is a silent no-op rather
        than an obvious one.

    ⚠️ Two animations, not one, because they are pinned to different things:
    the badge glows (it is the label) and the rail dot rings (it is the
    position). Pulsing them on one keyframe made the row read as flashing.

    Both take the event's own colour through `--ev-now`, set on the row.
    `color-mix()` rather than a hex-alpha suffix: the colour arrives as a
    variable, and `var(--ev-now)66` is not a colour — it is an invalid
    declaration the browser drops on the floor, which is the exact trap
    Design Rule #8 records for gradients.
--}}
@once
<style>
    /*  The NOW badge: a glow that breathes out of the badge itself, so the
        word is the thing that is alive rather than a box around it. */
    @keyframes evNowGlow {
        0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ev-now, #7c3aed) 50%, transparent),
                               0 0 7px 0 color-mix(in srgb, var(--ev-now, #7c3aed) 40%, transparent); }
        50%      { box-shadow: 0 0 0 4px transparent,
                               0 0 15px 3px color-mix(in srgb, var(--ev-now, #7c3aed) 80%, transparent); }
    }
    .ev-now { animation: evNowGlow 1.9s ease-in-out infinite; }

    /*  The rail dot: a ring that expands and fades — the "live" pulse, and the
        same shape as `.m-attn` in app.css, which is the platform's own
        vocabulary for "look here". That one fires three times on arrival; this
        one does not stop, because the fact it is reporting does not either.

        In step with the badge (the same 1.9s), so the row breathes once rather
        than twice. */
    @keyframes evNowRing {
        0%, 100% { box-shadow: 0 0 0 4px color-mix(in srgb, var(--ev-now, #7c3aed) 22%, transparent); }
        50%      { box-shadow: 0 0 0 9px color-mix(in srgb, var(--ev-now, #7c3aed) 0%, transparent); }
    }
    .ev-now-dot { animation: evNowRing 1.9s ease-in-out infinite; }

    /*  The label itself. A text-shadow rather than a box: the words are the
        thing that is happening ("Registration closes"), and a plate around
        them would make the row a second badge. It breathes on the SAME 1.9s
        as the badge and the dot, so the three read as one thing being alive
        rather than three things blinking.

        Peaks at a soft bloom and returns to nothing — a glow that never fully
        leaves reads as a rendering fault rather than an animation. */
    @keyframes evNowText {
        0%, 100% { text-shadow: 0 0 0 transparent; }
        50%      { text-shadow: 0 0 11px color-mix(in srgb, var(--ev-now, #7c3aed) 65%, transparent); }
    }
    .ev-now-text { animation: evNowText 1.9s ease-in-out infinite; }

    /*  Reduced motion keeps the MEANING and drops the movement: the badge and
        the dot hold the ring they would have pulsed to, so "this is now" is
        still said, just not repeatedly. */
    @media (prefers-reduced-motion: reduce) {
        .ev-now {
            animation: none;
            box-shadow: 0 0 8px 1px color-mix(in srgb, var(--ev-now, #7c3aed) 55%, transparent);
        }
        .ev-now-dot {
            animation: none;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--ev-now, #7c3aed) 22%, transparent);
        }
        .ev-now-text {
            animation: none;
            text-shadow: 0 0 9px color-mix(in srgb, var(--ev-now, #7c3aed) 45%, transparent);
        }
    }
</style>
@endonce
