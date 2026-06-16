<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Club room chat — one group Conversation (type=club) per club, with every
 * member as a participant. Posting/attachments/edit/delete reuse the Messenger
 * endpoints (the room IS a Conversation); this controller adds the room list,
 * the group-aware thread, moderation (kick/block/unblock), and mute/leave.
 */
class ClubChatController extends Controller
{
    /** List the rooms for clubs the current user belongs to (device-aware). */
    public function index()
    {
        $me = Auth::user();

        // Clubs the user can chat in: ones they're a member of, plus ones they
        // own or administer (owners/admins often aren't enrolled as members).
        $ids = $me->memberClubs()->pluck('tenants.id')
            ->merge(Tenant::where('owner_user_id', $me->id)->pluck('id'))
            ->merge(DB::table('user_roles')->where('user_id', $me->id)->whereNotNull('tenant_id')->pluck('tenant_id'))
            ->unique()->values();

        $clubs = Tenant::whereIn('id', $ids)->get(['id', 'club_name', 'logo']);

        $rooms = $clubs->map(function (Tenant $club) use ($me) {
            $room = Conversation::firstWhere(['type' => 'club', 'tenant_id' => $club->id]);
            $last = $room?->latestMessage;
            $pivot = $room?->participants()->where('users.id', $me->id)->first()?->pivot;

            return (object) [
                'club_id'     => $club->id,
                'name'        => $club->club_name,
                'logo'        => $club->logo ? asset('storage/' . $club->logo) : null,
                'initial'     => strtoupper(mb_substr($club->club_name, 0, 1)),
                'last_body'   => $last ? ($last->deleted_at ? 'message deleted' : ($last->attachment_kind ? '📎 attachment' : \Illuminate\Support\Str::limit((string) $last->body, 40))) : 'No messages yet',
                'last_at'     => $last?->created_at?->diffForHumans(null, true, true),
                'muted'       => (bool) ($pivot->muted ?? false),
                'blocked'     => (bool) ($pivot->blocked ?? false),
            ];
        })->values();

        $view = request()->attributes->get('is_mobile') ? 'club-chat.mobile.index' : 'club-chat.index';

        return view(view()->exists($view) ? $view : 'club-chat.mobile.index', ['rooms' => $rooms]);
    }

    /** Open a specific club's room page. */
    public function room(Tenant $club)
    {
        [$room] = $this->accessibleRoom($club);
        $me = Auth::id();

        $room->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        $view = request()->attributes->get('is_mobile') ? 'club-chat.mobile.room' : 'club-chat.room';

        return view(view()->exists($view) ? $view : 'club-chat.mobile.room', [
            'club'        => $club,
            'roomId'      => $room->id,
            'isModerator' => $this->isModerator($club),
        ]);
    }

    /** Group thread JSON (each message carries its sender). */
    public function thread(Tenant $club)
    {
        [$room] = $this->accessibleRoom($club);
        $me = (int) Auth::id();

        $hidden = DB::table('message_hides')->where('user_id', $me)->pluck('message_id');

        $messages = $room->messages()->with('sender:id,full_name,name,profile_picture')
            ->whereNotIn('id', $hidden)
            ->orderBy('id')->get();

        $room->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        return response()->json([
            'success'     => true,
            'messages'    => $messages->map(fn ($m) => $this->presentMessage($m, $me))->values(),
            'isModerator' => $this->isModerator($club),
        ]);
    }

    /** Members list + their moderation status (for the admin panel / tap-to-DM). */
    public function members(Tenant $club)
    {
        [$room] = $this->accessibleRoom($club);
        $me     = (int) Auth::id();
        $isMod  = $this->isModerator($club);

        $members = $room->participants()
            ->get(['users.id', 'full_name', 'name', 'profile_picture'])
            ->map(function ($u) use ($me, $isMod) {
                return [
                    'id'      => $u->id,
                    'name'    => $u->full_name ?? $u->name ?? 'User',
                    'avatar'  => $u->profile_picture ? asset('storage/' . $u->profile_picture) : null,
                    'initial' => strtoupper(mb_substr($u->full_name ?? $u->name ?? 'U', 0, 1)),
                    'me'      => $u->id === $me,
                    'blocked' => $isMod ? (bool) $u->pivot->blocked : false,
                    'banned'  => $isMod ? ($u->pivot->banned_until && now()->lt($u->pivot->banned_until)) : false,
                ];
            })->values();

        return response()->json(['success' => true, 'members' => $members, 'isModerator' => $isMod]);
    }

    /* ── moderation (club admins only) ── */

    public function kick(Request $request, Tenant $club, \App\Models\User $user)
    {
        $room = $this->moderatorRoom($club, $user);
        $mins = (int) $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:43200']])['minutes'];
        $room->participants()->updateExistingPivot($user->id, ['banned_until' => now()->addMinutes($mins)]);

        return response()->json(['success' => true]);
    }

    public function block(Tenant $club, \App\Models\User $user)
    {
        $room = $this->moderatorRoom($club, $user);
        $room->participants()->updateExistingPivot($user->id, ['blocked' => true]);

        return response()->json(['success' => true]);
    }

    public function unblock(Tenant $club, \App\Models\User $user)
    {
        $room = $this->moderatorRoom($club, $user);
        $room->participants()->updateExistingPivot($user->id, ['blocked' => false, 'banned_until' => null]);

        return response()->json(['success' => true]);
    }

    /* ── per-member preferences ── */

    public function mute(Request $request, Tenant $club)
    {
        [$room] = $this->accessibleRoom($club);
        $muted  = (bool) $request->boolean('muted');
        $room->participants()->updateExistingPivot(Auth::id(), ['muted' => $muted]);

        return response()->json(['success' => true, 'muted' => $muted]);
    }

    public function leave(Tenant $club)
    {
        $room = Conversation::firstWhere(['type' => 'club', 'tenant_id' => $club->id]);
        if ($room) {
            $room->participants()->updateExistingPivot(Auth::id(), ['left_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /** Ensure the room exists, the user may use it, and return [room, pivot]. */
    private function accessibleRoom(Tenant $club): array
    {
        $me = (int) Auth::id();
        abort_unless($this->isMember($club, $me) || $this->isModerator($club), 403, 'Not a member of this club.');

        $room  = Conversation::findOrCreateClubRoom($club);
        $pivot = $room->participants()->where('users.id', $me)->first()?->pivot;

        // Rejoin if they had left.
        if ($pivot && $pivot->left_at) {
            $room->participants()->updateExistingPivot($me, ['left_at' => null]);
        }

        abort_if($pivot && $pivot->blocked, 403, 'You are blocked from this chat.');
        abort_if($pivot && $pivot->banned_until && now()->lt($pivot->banned_until), 403, 'You have been removed from this chat temporarily.');

        return [$room, $pivot];
    }

    private function moderatorRoom(Tenant $club, \App\Models\User $target): Conversation
    {
        abort_unless($this->isModerator($club), 403);
        $room = Conversation::findOrCreateClubRoom($club);
        abort_unless($room->participants()->where('users.id', $target->id)->exists(), 404);

        return $room;
    }

    private function isMember(Tenant $club, int $userId): bool
    {
        return Membership::where('tenant_id', $club->id)->where('user_id', $userId)->exists();
    }

    private function isModerator(Tenant $club): bool
    {
        $u = Auth::user();
        return $u->isSuperAdmin()
            || $club->owner_user_id === $u->id
            || $u->isClubAdmin($club->id);
    }

    private function presentMessage(Message $m, int $meId): array
    {
        $deleted = $m->deleted_at !== null;
        $mine    = (int) $m->sender_id === $meId;
        $hasAtt  = $m->attachment_kind !== null && ! $deleted;
        $expired = $hasAtt && $m->attachment_path === null;
        $s       = $m->sender;
        $sname   = $s?->full_name ?? $s?->name ?? 'User';

        return [
            'id'      => $m->id,
            'body'    => $deleted ? null : $m->body,
            'mine'    => $mine,
            'time'    => $m->created_at->format('g:i A'),
            'edited'  => $m->edited_at !== null && ! $deleted,
            'deleted' => $deleted,
            'can_edit' => $mine && ! $deleted && ! $hasAtt,
            'sender'  => [
                'id'      => $m->sender_id,
                'name'    => $sname,
                'avatar'  => $s?->profile_picture ? asset('storage/' . $s->profile_picture) : null,
                'initial' => strtoupper(mb_substr($sname, 0, 1)),
            ],
            'kind'               => $hasAtt ? $m->attachment_kind : null,
            'attachment_expired' => $expired,
            'attachment'         => ($hasAtt && ! $expired) ? [
                'url'  => route('messages.attachment', [$m->conversation_id, $m->id]),
                'name' => $m->attachment_name,
                'mime' => $m->attachment_mime,
                'size' => (int) $m->attachment_size,
            ] : null,
        ];
    }
}
