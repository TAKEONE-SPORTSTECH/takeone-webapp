<?php

namespace App\Clubs\Controllers;

use App\Clubs\Models\Business;
use App\Http\Controllers\Controller;

use App\Clubs\Support\ChainDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
    /**
     * /business — which business do you mean?
     *
     * An owner of an approved business goes to its dashboard; anyone without
     * one goes to setup. A super-admin, who may look at every business, is
     * taken straight in when there is only one and otherwise picks.
     */
    public function home(Request $request)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return $user->hasApprovedBusiness()
                ? redirect()->route('business.dashboard')
                : redirect()->route('business.setup');
        }

        $businesses = Business::approved()
            ->with('owner:id,full_name')
            ->withCount('clubs')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo', 'owner_user_id']);

        if ($businesses->count() === 1) {
            return redirect()->route('business.dashboard', ['b' => $businesses->first()->slug]);
        }

        return view('clubs::business.picker', ['businesses' => $businesses]);
    }

    /**
     * The chain dashboard — combined performance across all clubs.
     * Serves a SEPARATE mobile or desktop Blade file (CLAUDE.md device rule).
     */
    public function index(Request $request, ChainDashboardService $service): View
    {
        // Provided by the EnsureHasBusiness middleware.
        $business = $request->attributes->get('business') ?? $request->user()->ownedBusiness;

        // Entering the dashboard implies Business mode.
        session(['view_mode' => 'business']);

        $data = $service->build($business);

        $isMobile = $request->attributes->get('is_mobile', false);

        // Someone else's business (a super-admin looking in) is read only.
        $readOnly = (int) $business->owner_user_id !== (int) $request->user()->id;

        $view = $isMobile ? 'clubs::business.mobile.dashboard' : 'clubs::business.desktop.dashboard';

        return view($view, [
            'business' => $business,
            'clubs' => $data['clubs'],
            'totals' => $data['totals'],
            'revenueChart' => $data['revenue_chart'],
            'readOnly' => $readOnly,
            'dashboardUrl' => $readOnly
                ? route('business.dashboard', ['b' => $business->slug])
                : route('business.dashboard'),
        ]);
    }
}
