<?php

namespace App\Support\Copilot;

/**
 * A skill that gathers its record over the conversation and creates it the
 * moment the user says they are done — no separate Create press.
 *
 * The user's "done" is the confirmation. Nothing else changes: the proposal is
 * still validated by stage(), signed with the acting user and an expiry, and
 * committed through the same single-use apply() that re-checks the signer, the
 * permission and the data. The engine only tells the widget to apply at once.
 *
 * Opt-in per skill, so a skill without it keeps the draft-then-Create card.
 */
interface CreatesWhenDone {}
