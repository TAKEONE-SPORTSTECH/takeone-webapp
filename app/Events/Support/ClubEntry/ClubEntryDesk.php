<?php

namespace App\Events\Support\ClubEntry;

use Illuminate\Http\Request;

/**
 * One host's back office behind the shared club-entry screens
 * (events::entry.club.page): rules → club → athletes → review → done → your
 * entries.
 *
 * The screens are the same for every host. The desk supplies what differs:
 * the event as the page reads it, the browser-side store the steps talk to
 * (`window.__TAKEONE_FED` — its `store` and the `urls` of the host's own JSON
 * doors), the scripts that implement that store, and the words.
 *
 * A desk decides nothing about who may READ the event — the page's controller
 * has already settled that. It may refuse to take entries at all by returning
 * null from ClubEntryDesks (closed, suspended, not a club event).
 */
interface ClubEntryDesk
{
    /**
     * Prove what the request already proves (a club's personal link, a signed-in
     * owner) before the page is drawn. Never throws for a bad token — it just
     * proves nothing.
     */
    public function prepare(Request $request): void;

    /**
     * Everything the page draws with:
     *   e, doors, boot, coverLanguages, languageUrls, console, strings,
     *   scripts (list of {src, defer}).
     *
     * @return array<string, mixed>
     */
    public function page(Request $request): array;

    /**
     * The club this reader has proved they act for, in the host's own terms —
     * {key, name} — or null while they have not. Read from the server's own
     * session; never from the request.
     *
     * @return array{key: string, name: ?string}|null
     */
    public function subject(Request $request): ?array;

    /**
     * The link to hand somebody so they can open this event and register
     * without an account (it grants the invitation pass), or null when the
     * host has none. What the page's Share control sends for an event that is
     * not public.
     */
    public function invitationUrl(): ?string;
}
