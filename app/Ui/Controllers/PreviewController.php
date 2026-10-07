<?php

namespace App\Ui\Controllers;

use App\Http\Controllers\Controller;
use App\Ui\NewUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the new UI on or off for the signed-in super-admin's own session,
 * while NEW_UI=preview. With the flag `off` or `on` there is nothing to switch
 * and both doors answer 404, so they reveal nothing.
 */
class PreviewController extends Controller
{
    /** A one-screen switch for the super-admin: on, off, and where it stands. */
    public function show(): \Illuminate\View\View
    {
        abort_unless(NewUi::mode() === 'preview' && NewUi::canPreview(), 404);

        return view('ui::preview', ['on' => NewUi::active()]);
    }

    public function on(Request $request): RedirectResponse
    {
        return $this->set($request, true);
    }

    public function off(Request $request): RedirectResponse
    {
        return $this->set($request, false);
    }

    private function set(Request $request, bool $value): RedirectResponse
    {
        abort_unless(NewUi::mode() === 'preview' && NewUi::canPreview(), 404);

        $request->session()->put(NewUi::SESSION_KEY, $value);

        return $value ? redirect()->route('clubs.explore') : redirect()->route('me.new-ui');
    }
}
