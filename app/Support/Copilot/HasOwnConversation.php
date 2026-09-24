<?php

namespace App\Support\Copilot;

use App\Members\Models\User;

/**
 * A skill that runs its OWN conversation, with its own prompt, instead of the
 * shared Coach prompt: every assistant has a prompt of its own.
 *
 * The prompt is a template the skill owns (a file beside it), filled in each
 * turn with the fields gathered so far, the conversation and the new message.
 * The model answers in JSON — {extracted_fields, response, user_seems_unsure,
 * ready_to_create} — and the ENGINE, not the model, keeps the gathered fields
 * (signed and encrypted, round-tripped by the widget).
 *
 * Nothing about creation is relaxed: when the model says ready, the fields go
 * through the skill's own stage() and the same signed, single-use apply().
 */
interface HasOwnConversation extends CreatesWhenDone
{
    /** The prompt template, with {{CURRENT_FIELDS_JSON}}, {{CONVERSATION_HISTORY_JSON}}, {{USER_MESSAGE}} and any {{ME_*}}. */
    public function conversationPrompt(User $user, string $locale): string;

    /** Merge newly extracted fields into what was gathered: whitelist and normalise. Never trusted as-is. */
    public function absorb(array $fields, array $extracted, User $user): array;

    /**
     * Resolve what the platform can check itself (people named by email or name,
     * say) and report what it found, for the prompt's {{LOOKUP_RESULTS_JSON}}.
     * Keys starting with "_" are the skill's own bookkeeping, never shown as fields.
     *
     * @return array{fields: array, lookups: array, changed: bool}
     */
    public function resolve(array $fields, User $user): array;

    /**
     * Fill what is still missing so creation is never blocked — only with values
     * that are safe to invent. Returns the fields and the names that are placeholders.
     *
     * @return array{fields: array, placeholders: array<int, string>}
     */
    public function complete(array $fields, User $user): array;
}
