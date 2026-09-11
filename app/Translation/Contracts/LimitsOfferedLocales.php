<?php

namespace App\Translation\Contracts;

/**
 * A record whose owner chooses which languages it is OFFERED in.
 *
 * Separate from TranslatableContent on purpose. That contract says "this thing
 * has words that can be translated"; this one says "and somebody gets to decide
 * which languages a reader is shown". Most translatable things have no such
 * owner — a shared lang file has nobody to ask — so the two are not one
 * interface, and `Translations::offered()` falls back to every served language
 * for anything that does not implement this.
 *
 * ⚠️ It limits what is OFFERED, never what is stored. Hiding a language leaves
 * its words exactly where they are, including every correction an organiser
 * typed by hand — which is the whole difference between this and
 * `Translations::remove()`, and the reason it was asked for (2026-09-10): an
 * organiser wanting a shorter list on their poster should not have to throw
 * away work and pay to have it written again.
 */
interface LimitsOfferedLocales
{
    /**
     * The languages this record may be read in, or NULL for "all of them".
     *
     * NULL and an empty list both mean no restriction — an owner who has never
     * touched the setting, and one who un-ticked everything, both get the
     * default rather than a poster nobody can read. The source language is
     * added by `Translations::offered()`, so an implementation need not
     * remember to include it.
     *
     * @return array<int, string>|null
     */
    public function offeredLocales(): ?array;
}
