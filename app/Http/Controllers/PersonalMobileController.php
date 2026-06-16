<?php

namespace App\Http\Controllers;

use App\Models\ClubEvent;
use App\Models\ClubMemberSubscription;
use App\Models\ClubTimelinePost;
use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The Personal ("member") mobile experience — renders inside the shared
 * mobile shell (layouts/personal-mobile) so switching Personal⇄Business keeps
 * the same chrome (top bar + switcher dropdown + bottom tabs).
 */
class PersonalMobileController extends Controller
{
    private function clubIds()
    {
        return Auth::user()->memberClubs()->pluck('tenants.id');
    }

    public function home(): View
    {
        $posts = ClubTimelinePost::whereIn('tenant_id', $this->clubIds())
            ->where('status', 'published')
            ->with('tenant:id,club_name,logo')
            ->latest('posted_at')
            ->limit(20)
            ->get();

        return view('personal.home', compact('posts'));
    }

    public function schedule(): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'pending'])
            ->with(['package:id,name', 'tenant:id,club_name,logo'])
            ->get();

        return view('personal.schedule', compact('subscriptions'));
    }

    public function profile(): View
    {
        $user = Auth::user();
        $clubCount = $this->clubIds()->count();
        $activeSubs = ClubMemberSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])->count();

        return view('personal.profile', compact('user', 'clubCount', 'activeSubs'));
    }

    public function packages(): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->with(['package:id,name,cover_image,price', 'tenant:id,club_name,logo,currency'])
            ->latest()
            ->get();

        return view('personal.packages', compact('subscriptions'));
    }

    public function progress(): View
    {
        $goals = Goal::where('user_id', Auth::id())->latest()->get();
        $goalStats = [
            'completed'   => $goals->where('status', 'completed')->count(),
            'in_progress' => $goals->where('status', 'in_progress')->count(),
            'pending'     => $goals->whereNotIn('status', ['completed', 'in_progress'])->count(),
        ];

        return view('personal.progress', compact('goals', 'goalStats'));
    }

    public function payments(): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->with(['package:id,name', 'tenant:id,club_name,currency'])
            ->latest()
            ->get();

        $totalPaid = (float) $subscriptions->sum('amount_paid');
        $totalDue  = (float) $subscriptions->whereIn('payment_status', ['unpaid', 'pending_approval'])->sum('amount_due');

        return view('personal.payments', compact('subscriptions', 'totalPaid', 'totalDue'));
    }

    public function community(): View
    {
        $me  = Auth::user();
        $ids = $me->memberClubs()->pluck('tenants.id')
            ->merge(\App\Models\Tenant::where('owner_user_id', $me->id)->pluck('id'))
            ->merge(\Illuminate\Support\Facades\DB::table('user_roles')->where('user_id', $me->id)->whereNotNull('tenant_id')->pluck('tenant_id'))
            ->unique()->values();

        $rooms = \App\Models\Tenant::whereIn('id', $ids)->get(['id', 'club_name', 'logo'])->map(function ($club) {
            $room = \App\Models\Conversation::firstWhere(['type' => 'club', 'tenant_id' => $club->id]);
            $last = $room?->latestMessage;

            return (object) [
                'club_id'   => $club->id,
                'name'      => $club->club_name,
                'logo'      => $club->logo ? asset('storage/' . $club->logo) : null,
                'initial'   => strtoupper(mb_substr($club->club_name, 0, 1)),
                'last_body' => $last ? ($last->deleted_at ? 'message deleted' : ($last->attachment_kind ? '📎 attachment' : \Illuminate\Support\Str::limit((string) $last->body, 40))) : 'No messages yet',
                'last_at'   => $last?->created_at?->diffForHumans(null, true, true),
            ];
        })->values();

        return view('personal.community', ['rooms' => $rooms]);
    }

    public function events(): View
    {
        $events = ClubEvent::whereIn('tenant_id', $this->clubIds())
            ->where('is_archived', false)
            ->with('tenant:id,club_name,logo')
            ->orderBy('date')
            ->get()
            ->filter(fn ($e) => !$e->hasEnded())
            ->take(20)
            ->values();

        return view('personal.events', compact('events'));
    }

    public function settings(): View
    {
        $user = Auth::user();
        return view('personal.settings', compact('user'));
    }
}
