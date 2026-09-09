<?php

namespace App\Translation\Contracts;

/**
 * A record whose free text can be read in another language.
 *
 * The model decides what counts as WORDS — and the decision matters more than
 * it looks. A colour, a uuid, a price, an icon name and a status enum are all
 * strings on the same row, and handing any of them to a translator produces
 * either nonsense or a broken page. So nothing is inferred from column types:
 * a model lists what it means.
 */
interface TranslatableContent
{
    /**
     * The record's words, as a flat map of key => text.
     *
     * Keys are stable and dotted for lists — 'title', 'description',
     * 'requirements.0', 'requirements.1'. Stable because a key is what a stored
     * translation is filed under: rename one and its translations orphan.
     * Dotted because a list of bullet points must come back as a list, in
     * order, with one changed bullet able to go stale by itself.
     *
     * Empty values are the caller's to skip — see Translator::document().
     *
     * @return array<string, string>
     */
    public function translatableDocument(): array;

    /**
     * What the writer is telling the reader about, in a sentence or two of
     * plain English, plus the proper nouns that must survive untouched.
     *
     * This is the difference between a translation and a good one. "Open" in a
     * competition's title is not the "open" of an opening time; a "gi" is not a
     * garment to be described; "Victory BJJ Academy" is a name, not a claim.
     * The agent is told all of that here, by the model that knows it.
     *
     * @return array{summary: string, keep: array<int, string>}
     */
    public function translationContext(): array;

    /**
     * The language the record was WRITTEN in.
     *
     * Not assumed to be English. An organiser typing in Arabic is the case this
     * whole module exists for, and translating Arabic while believing it is
     * English produces confident nonsense. Falls back to the app's own default
     * when a record predates the column.
     */
    public function sourceLocale(): string;
}
