<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationDashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FederationDashboardController extends Controller
{
    /** /federation — take the owner to their own federation. */
    public function home(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $federation = Federation::approved()
            ->where('owner_user_id', $request->user()->id)
            ->oldest()
            ->first();

        abort_unless($federation, 404);

        return redirect()->route('federations.dashboard', $federation);
    }

    public function show(Request $request, Federation $federation, FederationDashboard $service): View
    {
        $this->ensureEnabled();

        // Same answer for "does not exist" and "not yours" — never confirm
        // that a uuid is real to somebody who may not see it.
        abort_unless($federation->isManagedBy($request->user()), 404);

        $data = $service->build($federation);

        $view = $request->attributes->get('is_mobile')
            ? 'federations::mobile.dashboard'
            : 'federations::desktop.dashboard';

        return view($view, ['federation' => $federation] + $data);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('federations.enabled'), 404);
    }
}
