<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Folder;
use App\DevBoard\Models\FolderShare;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Support\FriendInvites;
use App\DevBoard\Support\Notebooks;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sharing a TakeNote folder (behind TAKENOTE_SHARING). The owner of a notebook
 * shares one of its folders with a person by email; that person then works in
 * it — notes, cards, the board — fenced to that folder (Support\Notebooks).
 * Somebody with no TakeNote yet is sent the friends' invitation as well, when
 * the sharer may invite (FriendInvites), and the share waits for their address.
 *
 * Only from one's own notebook: inside a folder shared with you there is
 * nothing to share (every action here is a 404 there). The Folder binding is
 * notebook-scoped, so a folder of somebody else's is a 404 too.
 */
class TakeNoteShareController extends Controller
{
    private const MAX_PER_FOLDER = 20;

    public function index(Folder $folder): JsonResponse
    {
        $this->ownerOnly();

        return response()->json(['success' => true, 'shares' => $this->list($folder)]);
    }

    public function store(Request $request, Folder $folder): JsonResponse
    {
        $this->ownerOnly();
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:190']]);
        $me = $request->user();
        $email = Notebooks::email($data['email']);
        if ($email === Notebooks::email((string) $me->email)) {
            return $this->no('sh_self');
        }

        $account = User::whereRaw('lower(email) = ?', [$email])->first();
        $book = Notebook::find($folder->notebook_id);
        // somebody already in this whole notebook (its owner, or the main notebook's people) needs no share
        if ($account && $book && ((int) $book->owner_id === (int) $account->id
            || ($book->is_main && ($account->isSuperAdmin() || BoardMember::where('user_id', $account->id)->exists())))) {
            return $this->no('sh_has_access');
        }

        $share = FolderShare::where('folder_id', $folder->id)->where('email', $email)->first();
        if ($share && ! $share->revoked_at) {
            return $this->no('sh_already');
        }
        if (FolderShare::live()->where('folder_id', $folder->id)->count() >= self::MAX_PER_FOLDER) {
            return $this->no('sh_too_many');
        }

        // no TakeNote yet: they are invited to it too (their own private notebook), and the share waits for them
        $has = $account && $this->hasTakeNote($account);
        if (! $has) {
            if (! Notebooks::canInvite($me)) {
                return $this->no('sh_needs_takenote');
            }
            if (! FriendInvites::open($email, $me, $account)) {
                return response()->json(['success' => false, 'message' => __('devboard::messages.fr_too_many')], 422);
            }
        }

        // bound to the account now only when that account has PROVEN the address (an invite-link account may
        // carry an email nobody confirmed); otherwise it waits until the address is confirmed (Notebooks::bindShares)
        $bindNow = $has && $account->hasVerifiedEmail();
        $share ??= new FolderShare(['folder_id' => $folder->id, 'notebook_id' => $folder->notebook_id, 'email' => $email]);
        $share->forceFill([
            'user_id' => $bindNow ? $account->id : null,
            'invited_by' => $me->id,
            'revoked_at' => null,
        ])->save();

        if ($bindNow) {
            UserNotification::notifyUser($account->id, 'takenote_share', __('devboard::messages.sh_notice', ['name' => FriendInvites::first($me), 'folder' => $folder->name]), [
                'actor_id' => $me->id,
                'icon' => 'bi-folder-symlink',
                'action_url' => route('takenote.app', ['space' => $share->uuid]),
            ]);
        }

        return response()->json(['success' => true, 'message' => __('devboard::messages.sh_sent'), 'shares' => $this->list($folder)]);
    }

    public function destroy(Folder $folder, string $share): JsonResponse
    {
        $this->ownerOnly();
        $row = FolderShare::live()->where('folder_id', $folder->id)->where('uuid', $share)->firstOrFail();
        $row->forceFill(['revoked_at' => now()])->save(); // their next click is back in their own notebook

        return response()->json(['success' => true, 'shares' => $this->list($folder)]);
    }

    /** Opening a folder shared with you, or going back to your own notebook (no share). */
    public function space(Request $request): JsonResponse
    {
        abort_unless(Notebooks::sharingOn(), 404);
        $data = $request->validate(['share' => ['nullable', 'uuid']]);
        if (empty($data['share'])) {
            $request->session()->forget(Notebooks::SPACE);

            return response()->json(['success' => true]);
        }
        abort_unless(Notebooks::shareFor($request->user(), $data['share']), 404);
        $request->session()->put(Notebooks::SPACE, $data['share']);

        return response()->json(['success' => true]);
    }

    /**
     * The folders shared with this person, for their folders list — read past the notebook scope, by
     * their own shares only.
     *
     * @return array<int, array{uuid:string,name:string,color:?string,owner:string}>
     */
    public static function sharedWith(User $user): array
    {
        if (! Notebooks::sharingOn()) {
            return [];
        }
        Notebooks::bindShares($user);

        return DB::table('devboard_folder_shares as s')
            ->join('devboard_folders as f', fn ($j) => $j->on('f.id', '=', 's.folder_id')->on('f.notebook_id', '=', 's.notebook_id'))
            ->join('devboard_notebooks as n', 'n.id', '=', 's.notebook_id')
            ->leftJoin('users as u', 'u.id', '=', 'n.owner_id')
            ->where('s.user_id', $user->id)->whereNull('s.revoked_at')->whereNull('n.revoked_at')
            ->orderBy('f.name')
            ->get(['s.uuid', 'f.name', 'f.color', 'u.full_name'])
            ->map(fn ($r) => ['uuid' => $r->uuid, 'name' => $r->name, 'color' => $r->color,
                'owner' => trim(explode(' ', trim((string) $r->full_name))[0] ?? '') ?: (string) $r->full_name])
            ->values()->all();
    }

    private function list(Folder $folder): array
    {
        return FolderShare::live()->where('folder_id', $folder->id)->with('user:id,full_name')->orderBy('created_at')->get()
            ->map(fn (FolderShare $s) => [
                'uuid' => $s->uuid,
                'email' => $s->email,
                'name' => $s->user?->full_name,
                'joined' => $s->user_id !== null,
            ])->values()->all();
    }

    private function hasTakeNote(User $u): bool
    {
        return Notebook::live()->where('owner_id', $u->id)->exists()
            || $u->isSuperAdmin() || BoardMember::where('user_id', $u->id)->exists();
    }

    private function ownerOnly(): void
    {
        abort_unless(Notebooks::sharingOn() && Notebooks::fence() === null, 404);
    }

    private function no(string $key): JsonResponse
    {
        return response()->json(['success' => false, 'message' => __('devboard::messages.'.$key)], 422);
    }
}
