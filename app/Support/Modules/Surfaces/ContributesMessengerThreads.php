<?php

namespace App\Support\Modules\Surfaces;

use App\Members\Models\Conversation;
use App\Members\Models\Message;

/**
 * A module that owns a kind of Messenger thread.
 *
 * The Messenger is built around the 1:1 chat: the other participant IS the
 * thread's face. A module can also open threads in it that are not between
 * two people — a federation writing to a club's admins — by giving the
 * conversation its own `type` and answering, for that type, what the thread
 * looks like and what happens when someone posts in it.
 *
 * The Messenger asks; it never names the module. Direct chats are untouched:
 * nothing here is consulted for `type = 'direct'`.
 */
interface ContributesMessengerThreads
{
    /** @return array<int, string> the conversation types this module owns */
    public function messengerThreadTypes(): array;

    /**
     * The thread's face for this viewer, in the Messenger's partner shape:
     * id, uuid, slug, name, avatar, initial, blocked. A null uuid/slug means
     * there is no profile to open and nobody to block.
     */
    public function messengerPartner(Conversation $conversation, int $viewerId): array;

    /** Who wrote a message, shown over a bubble that is not the viewer's own. */
    public function messengerSenderName(Conversation $conversation, Message $message): ?string;

    /** A message was posted, edited or deleted here through the Messenger ('new'|'file'|'edit'|'delete'). */
    public function messengerMessage(Conversation $conversation, Message $message, string $action): void;
}
