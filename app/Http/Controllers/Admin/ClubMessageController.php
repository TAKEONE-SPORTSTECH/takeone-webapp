<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;

class ClubMessageController extends Controller
{
    use HandlesClubAuthorization;

    public function messages(Tenant $club)
    {
        $this->authorizeClub($club);
        $conversations = collect(); // TODO: Implement messaging
        $members       = Membership::where('tenant_id', $club->id)->with('user')->get();
        return view('admin.club.messages.index', compact('club', 'conversations', 'members'));
    }

    public function sendMessage(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        // TODO: Implement message sending

        return back()->with('success', 'Message sent successfully.');
    }
}
