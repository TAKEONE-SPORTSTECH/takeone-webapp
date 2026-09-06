{{--
    The public pages wear the product's design, so they sit on the product's
    GROUND too.

    The shell's own `--pg`/`--ink` are REDEFINED rather than overridden on
    <body>, because entry.layout sets those two inline and an inline style beats
    any class — and because redefining them here leaves the enrolment flow,
    which shares that layout and keeps its own lighter design, exactly as it was.
--}}
<style>
    :root {
        /* White, matching entry/partials/skin-style. `--ink` still comes from
           the product, because it is only ever used on the cards. */
        --pg:  #ffffff;
        --ink: var(--color-foreground);
    }
</style>
