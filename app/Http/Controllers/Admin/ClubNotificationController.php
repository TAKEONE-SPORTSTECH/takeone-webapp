<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendNotificationRequest;
use App\Jobs\SendClubNotification;
use App\Models\ClubNotification;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubNotificationController extends Controller
{
    use HandlesClubAuthorization;

    public function index(Tenant $club)
    {
        $this->authorizeClub($club);

        $notifications = ClubNotification::where('tenant_id', $club->id)
            ->with('sender')
            ->latest('sent_at')
            ->paginate(20);

        return view('admin.club.notifications.index', compact('club', 'notifications'));
    }

    public function store(SendNotificationRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        // Resolve recipients
        if ($request->recipient_type === 'all') {
            $recipients = Membership::where('tenant_id', $club->id)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();
        } else {
            $recipients = User::whereIn('id', $request->recipient_ids)->get();
        }

        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No recipients found.'], 422);
        }

        // Create notification log
        $notification = ClubNotification::create([
            'tenant_id'        => $club->id,
            'sender_user_id'   => Auth::id(),
            'subject'          => $request->subject,
            'message'          => $request->message,
            'recipient_type'   => $request->recipient_type,
            'recipient_count'  => $recipients->count(),
            'sent_at'          => now(),
        ]);

        // Dispatch job
        SendClubNotification::dispatch($notification, $recipients);

        return response()->json([
            'success' => true,
            'message' => 'Notification queued for ' . $recipients->count() . ' recipient(s).',
        ]);
    }

    public function markRead(Request $request)
    {
        $query = UserNotification::where('user_id', Auth::id())->where('is_read', false);

        if ($request->filled('notification_id')) {
            $query->where('id', $request->notification_id);
        }

        $query->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
