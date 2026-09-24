<?php

namespace App\Support\Copilot;

use App\Members\Models\User;

/**
 * One thing the assistant ("Coach") can create — a club, a federation, and
 * later an event, a package, a member…
 *
 * A skill is owned by the module that owns the record, and it creates that
 * record through the SAME service and the SAME validation its form uses: the
 * assistant is another way to fill the form, never a second way to write the
 * table. Nothing a skill does is trusted from the model: stage() validates
 * whatever the model proposed, and apply() is only ever given what stage()
 * returned, signed, after a human pressed Create — and it re-validates.
 */
interface CopilotSkill
{
    /** Stable key; the proposal tool is `propose_{key}`. e.g. 'create_club'. */
    public function key(): string;

    /** What it makes, in a few words, for the prompt and the card. */
    public function label(string $locale): string;

    /** May this user use it at all? Checked on every turn and again on apply. */
    public function allowedFor(User $user): bool;

    /** Route names where this is the obvious thing to offer first. */
    public function pages(): array;

    /** The prompt section: what the fields mean and how to fill them. */
    public function instructions(string $locale, User $user): string;

    /** JSON-schema `properties` and `required` of the proposal tool. */
    public function parameters(): array;

    public function required(): array;

    /** Extra READ tools (tool definitions, OpenAI shape). */
    public function lookupTools(): array;

    /** Answer one of lookupTools(). Must scope to what $user may see. */
    public function lookup(User $user, string $tool, array $args): array;

    /**
     * Validate a proposal.
     *
     * @return array{ok: bool, errors?: array<int, string>, data?: array, rows?: array<int, array{label: string, value: string, long?: bool, flag?: bool}>, title?: string}
     */
    public function stage(User $user, array $args, string $locale): array;

    /**
     * Create the record from a staged proposal (re-validated here).
     *
     * @return array{ok: bool, message?: string, title?: string, url?: string, open_label?: string}
     */
    public function apply(User $user, array $data): array;
}
