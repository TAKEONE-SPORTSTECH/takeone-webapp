<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubMessage;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubMessageController extends Controller
{
    use HandlesClubAuthorization;

    /** Messages inbox — conversation list + member directory for new chats. */
    public function messages(Tenant $club)
    {
        $this->authorizeClub($club);

        $conversations = $this->conversationsFor($club, (int) Auth::id());
        $members       = Membership::where('tenant_id', $club->id)->with('user')->get();

        return view(\App\Support\ClubView::pick('messages'), compact('club', 'conversations', 'members'));
    }

    /** Full thread between the admin and one member (JSON, marks inbound read). */
    public function conversation(Tenant $club, User $user)
    {
        $this->authorizeClub($club);
        abort_unless($this->isClubMember($club, $user->id), 403);
        $adminId = (int) Auth::id();

        $messages = ClubMessage::where('tenant_id', $club->id)
            ->where(fn ($q) => $q
                ->where(fn ($x) => $x->where('sender_id', $adminId)->where('recipient_id', $user->id))
                ->orWhere(fn ($x) => $x->where('sender_id', $user->id)->where('recipient_id', $adminId)))
            ->orderBy('created_at')
            ->get();

        // Mark the member's inbound messages as read now that the admin opened the thread.
        ClubMessage::where('tenant_id', $club->id)
            ->where('sender_id', $user->id)
            ->where('recipient_id', $adminId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success'  => true,
            'user'     => $this->presentUser($user),
            'messages' => $messages->map(fn ($m) => $this->presentMessage($m, $adminId))->values(),
        ]);
    }

    /** Send a message to a member; persists, then pushes realtime to the recipient. */
    public function sendMessage(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'message'      => ['required', 'string', 'max:5000'],
            'subject'      => ['nullable', 'string', 'max:255'],
        ]);

        // Recipient must be a member of THIS club — never an arbitrary user (IDOR).
        abort_unless($this->isClubMember($club, (int) $data['recipient_id']), 403);

        $adminId = (int) Auth::id();

        $message = ClubMessage::create([
            'tenant_id'    => $club->id,
            'sender_id'    => $adminId,
            'recipient_id' => $data['recipient_id'],
            'subject'      => $data['subject'] ?? null,
            'message'      => $data['message'],
            'is_read'      => false,
        ]);

        $this->pushRealtime($club, $message);

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Message sent.',
                'data'         => $this->presentMessage($message, $adminId),
                'recipient_id' => (int) $data['recipient_id'],
            ]);
        }

        return back()->with('success', 'Message sent successfully.');
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /** Guard: the given user belongs to this club (recipients/threads are scoped to members). */
    private function isClubMember(Tenant $club, int $userId): bool
    {
        return Membership::where('tenant_id', $club->id)
            ->where('user_id', $userId)
            ->exists();
    }

    /** Derive the conversation list (one row per other-participant) for an admin. */
    private function conversationsFor(Tenant $club, int $adminId)
    {
        $messages = ClubMessage::where('tenant_id', $club->id)
            ->where(fn ($q) => $q->where('sender_id', $adminId)->orWhere('recipient_id', $adminId))
            ->orderByDesc('created_at')
            ->get();

        $grouped = $messages->groupBy(
            fn ($m) => $m->sender_id === $adminId ? $m->recipient_id : $m->sender_id
        );

        $users = User::whereIn('id', $grouped->keys())->get()->keyBy('id');

        return $grouped->map(function ($msgs, $otherId) use ($adminId, $users) {
            $last   = $msgs->first();
            $unread = $msgs->where('recipient_id', $adminId)->where('is_read', false)->count();

            return (object) [
                'user_id'         => $otherId,
                'user'            => $users[$otherId] ?? null,
                'last_message'    => $last->message,
                'last_message_at' => $last->created_at,
                'unread'          => $unread > 0,
                'unread_count'    => $unread,
            ];
        })->values();
    }

    private function presentMessage(ClubMessage $m, int $adminId): array
    {
        return [
            'id'               => $m->id,
            'body'             => $m->message,
            'mine'             => $m->sender_id === $adminId,
            'is_read'          => $m->is_read,
            'created_at'       => $m->created_at->toIso8601String(),
            'created_at_human' => $m->created_at->diffForHumans(null, true, true),
            'time'             => $m->created_at->format('g:i A'),
        ];
    }

    private function presentUser(User $user): array
    {
        return [
            'id'     => $user->id,
            'name'   => $user->full_name ?? $user->name ?? 'Member',
            'avatar' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
            'initial'=> strtoupper(substr($user->full_name ?? $user->name ?? 'M', 0, 1)),
        ];
    }

    /** Best-effort realtime delivery to the recipient (DB row is source of truth). */
    private function pushRealtime(Tenant $club, ClubMessage $message): void
    {
        $sender = Auth::user();

        Realtime()->publishToUser((int) $message->recipient_id, 'messages', [
            'id'               => $message->id,
            'tenant_id'        => $club->id,
            'club_slug'        => $club->slug,
            'club_name'        => $club->club_name ?? 'Club',
            'from_id'          => (int) $message->sender_id,
            'from_name'        => $sender->full_name ?? $sender->name ?? 'Club',
            'from_avatar'      => $sender->profile_picture ? asset('storage/' . $sender->profile_picture) : null,
            'body'             => $message->message,
            'created_at_human' => 'just now',
        ]);
    }
}
