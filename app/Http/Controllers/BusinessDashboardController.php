<?php

namespace App\Http\Controllers;

use App\Services\ChainDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessDashboardController extends Controller
{
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

        $view = $isMobile ? 'business.mobile.dashboard' : 'business.desktop.dashboard';

        return view($view, [
            'business' => $business,
            'clubs' => $data['clubs'],
            'totals' => $data['totals'],
        ]);
    }
}
