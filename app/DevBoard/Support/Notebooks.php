<?php

namespace App\DevBoard\Support;

use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\FolderShare;
use App\DevBoard\Models\Notebook;
use App\Members\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Whose notebook a request works in — the ONE place that decides it.
 *
 * EnsureBoardAccess asks for() and sets the answer as the current notebook;
 * every card, folder, lane and label query is then scoped to it by the models
 * themselves (Concerns\InNotebook), and everything below a card through its
 * card (Concerns\InNotebookThroughCard). A uuid from somebody else's notebook
 * simply does not exist for you.
 *
 * Off the web (a console command, a queued job) there is no current notebook
 * and nothing is filtered — the reminders and the sweep work across all of
 * them. On the web with no current notebook, everything is hidden.
 */
final class Notebooks
{
    private static ?int $current = null;

    /**
     * A SHARED FOLDER (devboard_folder_shares): the person works in the owner's notebook, fenced to that
     * folder and the folders inside it — the fence is the list of folder ids every card and folder query is
     * then limited to (Concerns\InNotebook, Concerns\InNotebookThroughCard). Null: no fence, their own notebook.
     */
    private static ?array $fence = null;

    private static ?FolderShare $share = null;

    /** The session key holding which shared folder this person has open (a share's uuid). */
    public const SPACE = 'takenote.space';

    /** The notebook this person works in, or null: no TakeNote for them. */
    public static function for(?User $user): ?Notebook
    {
        if (! $user || ! BoardAccess::enabled()) {
            return null;
        }

        // their own (the owner's is the main one)
        $own = Notebook::live()->where('owner_id', $user->id)->first();
        if ($own) {
            return $own;
        }

        // an invitation waiting for them — opened only by a verified account with that email
        if (self::friendsOn() && $user->email && $user->hasVerifiedEmail()) {
            $invite = Notebook::live()->whereNull('owner_id')->where('email', self::email($user->email))->first();
            if ($invite) {
                $invite->forceFill(['owner_id' => $user->id, 'joined_at' => now()])->save();

                return $invite;
            }
        }

        // the main notebook's people, as before: listed board members and super-admins
        if ($user->isSuperAdmin() || BoardMember::where('user_id', $user->id)->exists()) {
            return self::main();
        }

        return null;
    }

    /** A link's secret: 40 letters and digits, kept on the server only as its SHA-256. */
    public static function linkHash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The waiting notebook a link opens, or null — used up, removed, or never real all look the same. */
    public static function findLink(string $token): ?Notebook
    {
        if (! self::friendsOn() || ! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        return Notebook::live()->whereNull('owner_id')->where('invite_hash', self::linkHash($token))->first();
    }

    /**
     * The link becomes this person's notebook, and is used up. Atomic: of two people opening the same
     * link at the same moment, one gets it and the other is told it is gone (false).
     */
    public static function claimLink(Notebook $book, User $user): bool
    {
        return Notebook::whereKey($book->id)->whereNull('owner_id')->whereNull('revoked_at')->whereNotNull('invite_hash')
            ->update(['owner_id' => $user->id, 'joined_at' => now(), 'invite_hash' => null, 'via_link' => true, 'updated_at' => now()]) === 1;
    }

    /**
     * Whether TakeNote opens for this person before their email is confirmed: they came in through an
     * invite link the owner handed them. Only TakeNote asks this (Middleware\VerifiedOrLinkFriend).
     */
    public static function joinedByLink(?User $user): bool
    {
        return $user && self::friendsOn()
            && Notebook::live()->where('owner_id', $user->id)->where('via_link', true)->exists();
    }

    public static function main(): ?Notebook
    {
        return Notebook::where('is_main', true)->first();
    }

    /** Who may invite friends: the people of the main notebook. */
    public static function canInvite(?User $user): bool
    {
        return self::friendsOn() && (bool) self::for($user)?->is_main;
    }

    public static function friendsOn(): bool
    {
        return (bool) config('devboard.friends');
    }

    public static function email(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function set(?Notebook $notebook, ?array $fence = null, ?FolderShare $share = null): void
    {
        self::$current = $notebook?->id;
        self::$fence = $notebook ? $fence : null;
        self::$share = $notebook ? $share : null;
    }

    public static function sharingOn(): bool
    {
        return (bool) config('devboard.sharing');
    }

    /**
     * Where this request works: [notebook, fence, share]. Their own notebook — or, when they have opened a
     * folder somebody shared with them and that share is still live, the owner's notebook fenced to it.
     * Asked afresh on every request, so removing someone takes effect on their next click; anything that
     * does not check out drops back to their own notebook and forgets the choice.
     *
     * @return array{0:?Notebook,1:?array,2:?FolderShare}
     */
    public static function resolve(?User $user, ?Request $request = null): array
    {
        $own = self::for($user);
        if (! $own || ! self::sharingOn() || ! $request || ! $request->hasSession()) {
            return [$own, null, null];
        }
        $uuid = $request->session()->get(self::SPACE);
        if (! is_string($uuid) || $uuid === '') {
            return [$own, null, null];
        }
        $share = self::shareFor($user, $uuid);
        $book = $share ? Notebook::live()->whereKey($share->notebook_id)->whereNotNull('owner_id')->first() : null;
        if (! $share || ! $book || $book->id === $own->id) {
            $request->session()->forget(self::SPACE);

            return [$own, null, null];
        }

        return [$book, self::folderTree($share->folder_id, $book->id), $share];
    }

    /** A live share of a folder that still exists, held by this person — or null, whatever the reason. */
    public static function shareFor(User $user, string $uuid): ?FolderShare
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return null;
        }
        self::bindShares($user);

        return FolderShare::live()->where('uuid', $uuid)->where('user_id', $user->id)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('devboard_folders')
                ->whereColumn('devboard_folders.id', 'devboard_folder_shares.folder_id')
                ->whereColumn('devboard_folders.notebook_id', 'devboard_folder_shares.notebook_id'))
            ->first();
    }

    /** Shares made to an email before its owner had TakeNote become theirs once that address is confirmed. */
    public static function bindShares(User $user): void
    {
        if (! self::sharingOn() || ! $user->email || ! $user->hasVerifiedEmail()) {
            return;
        }
        FolderShare::live()->whereNull('user_id')->where('email', self::email($user->email))->update(['user_id' => $user->id, 'updated_at' => now()]);
    }

    /** A folder and every folder inside it (the notebook's own rows, read past the scope). */
    public static function folderTree(int $folderId, int $notebookId): array
    {
        $ids = [(int) $folderId];
        $edge = $ids;
        for ($i = 0; $edge && $i < 12; $i++) {
            $edge = array_map('intval', DB::table('devboard_folders')->where('notebook_id', $notebookId)->whereIn('parent_id', $edge)->pluck('id')->all());
            $edge = array_values(array_diff($edge, $ids));
            $ids = array_merge($ids, $edge);
        }

        return $ids;
    }

    /** The fence this request works inside (folder ids), or null for a whole notebook of one's own. */
    public static function fence(): ?array
    {
        if (self::$current !== null) {
            return self::$fence;
        }
        self::scopeId(); // worked out lazily for a query before the middleware, like the notebook itself
        $request = app()->bound('request') ? app('request') : null;

        return $request ? $request->attributes->get('devboard.fence') : null;
    }

    /** The share this request works in, or null. */
    public static function share(): ?FolderShare
    {
        if (self::$current !== null) {
            return self::$share;
        }
        self::scopeId();
        $request = app()->bound('request') ? app('request') : null;

        return $request ? $request->attributes->get('devboard.share') : null;
    }

    /**
     * The id to scope by: the current notebook — worked out from the signed-in person when a query runs
     * before EnsureBoardAccess has set it (route model binding runs first), once per request; nothing
     * off the web; an impossible one on the web for somebody with no notebook.
     */
    public static function scopeId(): ?int
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $request = app()->bound('request') ? app('request') : null;
        if ($request && ($user = $request->user())) {
            if (! $request->attributes->has('devboard.notebook')) {
                [$book, $fence, $share] = self::resolve($user, $request);
                $request->attributes->set('devboard.notebook', $book?->id ?? 0);
                $request->attributes->set('devboard.fence', $fence);
                $request->attributes->set('devboard.share', $share);
            }

            return $request->attributes->get('devboard.notebook');
        }

        return app()->runningInConsole() ? null : 0;
    }

    public static function currentId(): ?int
    {
        return self::$current;
    }
}
