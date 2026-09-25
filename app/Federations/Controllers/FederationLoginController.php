<?php

namespace App\Federations\Controllers;

use App\Federations\Services\FederationDirectory;
use App\Http\Controllers\Controller;
use App\Members\Auth\AuthenticatedSessionController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The federation's own door: /federation/login.
 *
 * It LOOKS like the federation product — the designed prototype's staff sign-in
 * (navy ground, gold-edged card) — so federation staff know where they are. It
 * is not a second login system: the password goes through the platform's own
 * AuthenticatedSessionController::store() (lockout, throttle, email
 * verification, 2FA hand-off, activity log), exactly as the public event
 * page's sign-in does. Only where its answers LAND is changed, so a wrong
 * password, an unverified email or the 2FA step never drops a federation user
 * onto the platform's generic screens.
 *
 * The 2FA step is this module's page too, and it posts the code to the
 * platform's own verifier (`two-factor.verify`); that endpoint returns to the
 * page that sent it on a wrong code, and to `url.intended` — /federation — on
 * a right one.
 *
 * Every destination is a named route of this module or the session's own
 * intended URL, never something from the request: no open redirect.
 */
class FederationLoginController extends Controller
{
    public function __construct(private FederationDirectory $directory) {}

    public function show(Request $request): Response
    {
        abort_unless(config('federations.enabled'), 404);

        if ($request->user()) {
            return redirect()->route('federations.home');
        }

        return response()->view('federations::auth.login', ['step' => 'creds'])
            ->header('Cache-Control', 'no-store, private');
    }

    public function signIn(Request $request, AuthenticatedSessionController $auth): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(config('federations.enabled'), 404);

        $landing = $this->landing($request);
        $request->session()->put('url.intended', $landing);
        $response = $auth->store($request);

        // Signed in, no 2FA step: the dashboard.
        if ($me = Auth::user()) {
            // Home decides what a signed-in account sees — including "you run
            // no federation" — inside the surface.
            $request->session()->forget('url.intended');

            return redirect()->to($landing);
        }

        if ($response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            if ($target === route('two-factor.challenge')) {
                return redirect()->route('federations.login.verify');
            }
            if ($target === route('login') || $target === route('login.choose')) {
                // Unverified email / household choice: the platform's screens
                // handle those; everything else (wrong password, lockout) is a
                // `back()` and already returns here.
                return $target === route('login') ? redirect()->route('federations.login') : $response;
            }
        }

        return $response;
    }

    /**
     * Where a sign-in lands: the dashboard screen the visitor was sent here
     * from (a shared link to /federation/events/<uuid>/draw), or home. Only an
     * address of this dashboard on this host qualifies — never anything else
     * the session or the request might hold, so this is no open redirect.
     */
    private function landing(Request $request): string
    {
        $u = (string) $request->session()->get('url.intended', '');
        $host = parse_url($u, PHP_URL_HOST);
        $path = (string) parse_url($u, PHP_URL_PATH);
        $query = (string) parse_url($u, PHP_URL_QUERY);

        if ((! $host || $host === $request->getHost()) && preg_match(FederationAppController::DEEP_LINK, $path)) {
            return url($path).(preg_match('/^[a-z]+=[a-z-]{1,20}(&[a-z]+=[a-z-]{1,20})?$/', $query) ? '?'.$query : '');
        }

        return route('federations.home');
    }

    /** The 2FA step, in the federation's own look. */
    public function verify(Request $request): Response
    {
        abort_unless(config('federations.enabled'), 404);

        if (! $request->session()->has('two_factor.user_id')) {
            return redirect()->route('federations.login');
        }
        $request->session()->put('url.intended', $this->landing($request));

        return response()->view('federations::auth.login', ['step' => 'otp'])
            ->header('Cache-Control', 'no-store, private');
    }
}
