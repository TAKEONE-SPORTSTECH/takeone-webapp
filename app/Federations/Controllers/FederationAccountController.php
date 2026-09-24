<?php

namespace App\Federations\Controllers;

use App\Federations\Notifications\FederationResetPassword;
use App\Federations\Services\FederationDirectory;
use App\Federations\Support\FederationBrand;
use App\Http\Controllers\Controller;
use App\Members\Auth\NewPasswordController;
use App\Members\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Everything about a staff member's own account that the platform would
 * otherwise show on its own pages: signing out, a forgotten password, and
 * 2FA. Here, in the federation's shell, so the federation stays sealed.
 *
 * The platform's machinery does the work — the session guard, the password
 * broker and NewPasswordController (as the event sign-in reuses the login
 * controller), and App\Members\Support\TwoFactor for 2FA.
 */
class FederationAccountController extends Controller
{
    public function __construct(private FederationDirectory $directory) {}

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('federations.login');
    }

    // ------------------------------------------------------ password reset

    public function forgot(): View
    {
        $this->ensureEnabled();

        return view('federations::auth.forgot');
    }

    /** Always the same answer, whether or not the email has an account (no enumeration). */
    public function sendLink(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $request->validate(['email' => ['required', 'email']]);
        Password::broker()->sendResetLink(['email' => $request->input('email')], function ($user, $token) {
            $user->notify(new FederationResetPassword($token));
        });

        return back()->with('status', __('federations::messages.pw_link_sent'));
    }

    public function resetForm(Request $request, string $token): View
    {
        $this->ensureEnabled();

        return view('federations::auth.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    /** The platform's reset handler; its success answer (the platform sign-in) lands on ours. */
    public function reset(Request $request, NewPasswordController $platform): RedirectResponse
    {
        $this->ensureEnabled();
        $response = $platform->store($request);
        if ($response instanceof RedirectResponse && $response->getTargetUrl() === route('login')) {
            $response->setTargetUrl(route('federations.login'));
        }

        return $response;
    }

    // ------------------------------------------------------------- 2FA

    public function security(Request $request, TwoFactor $tf): View
    {
        $user = $request->user();
        $state = $user->hasTwoFactorEnabled() ? 'on' : ($tf->pending($user) && session('fed_2fa_setup') ? 'setup' : 'off');

        return $this->securityView($request, $state, ['setup' => $state === 'setup' ? $tf->pending($user) : null]);
    }

    public function setup(Request $request, TwoFactor $tf): RedirectResponse
    {
        $tf->beginSetup($request->user());

        return redirect()->route('federations.security')->with('fed_2fa_setup', true);
    }

    public function confirm(Request $request, TwoFactor $tf): View|RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $codes = $tf->confirm($request->user(), (string) $request->input('code'));
        if ($codes === null) {
            return redirect()->route('federations.security')->with('fed_2fa_setup', true)->withErrors(['code' => __('federations::messages.sec_bad_code')]);
        }
        // This session already proved who it is; keep it signed in with 2FA now on.
        $request->session()->put('two_factor.verified', true);
        activity('auth')->causedBy($request->user())->log('2FA enabled (federation dashboard)');

        return $this->securityView($request, 'codes', ['codes' => $codes]);
    }

    public function codes(Request $request, TwoFactor $tf): View|RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $codes = $tf->regenerate($request->user(), (string) $request->input('code'));

        return $codes === null
            ? redirect()->route('federations.security')->withErrors(['code' => __('federations::messages.sec_bad_code')])
            : $this->securityView($request, 'codes', ['codes' => $codes]);
    }

    public function disable(Request $request, TwoFactor $tf): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:11']]);
        if (! $tf->disable($request->user(), (string) $request->input('code'))) {
            return redirect()->route('federations.security')->withErrors(['code' => __('federations::messages.sec_bad_code')]);
        }
        activity('auth')->causedBy($request->user())->log('2FA disabled (federation dashboard)');

        return redirect()->route('federations.security')->with('status', __('federations::messages.sec_turned_off'));
    }

    private function securityView(Request $request, string $state, array $extra): View
    {
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);

        return view('federations::auth.security', ['state' => $state, 'user' => $request->user(), 'federation' => $access['root'],
            'favicon' => FederationBrand::favicon($access['root'])] + $extra + ['codes' => [], 'setup' => null]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('federations.enabled'), 404);
    }
}
