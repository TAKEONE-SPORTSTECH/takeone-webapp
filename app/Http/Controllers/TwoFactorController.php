<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show the Security Settings page.
     */
    public function show()
    {
        return view('security.index', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Begin 2FA setup: generate a secret and show the QR code.
     * Does NOT enable 2FA yet — the user must confirm with a valid code first.
     */
    public function setup(Request $request)
    {
        $user = Auth::user();

        // Generate a fresh secret and store it (unconfirmed) so the QR renders.
        $secret = $this->google2fa->generateSecretKey();
        $user->two_factor_secret        = encrypt($secret);
        $user->two_factor_confirmed_at  = null; // not yet confirmed
        $user->save();

        $qrCodeSvg = $this->buildQrCode($user->email, $secret);

        return view('security.setup', [
            'user'      => $user,
            'secret'    => $secret,
            'qrCodeSvg' => $qrCodeSvg,
        ]);
    }

    /**
     * Confirm 2FA setup by verifying the first TOTP code.
     * On success, generate and show recovery codes once.
     */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user   = Auth::user();
        $secret = decrypt($user->two_factor_secret);

        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->two_factor_confirmed_at   = now();
        $user->save();

        return view('security.recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'fresh'         => true,
        ]);
    }

    /**
     * Disable 2FA — requires the current TOTP code to confirm.
     */
    public function disable(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->with('error', '2FA is not enabled.');
        }

        $secret = decrypt($user->two_factor_secret);
        $valid  = $this->google2fa->verifyKey($secret, $request->code)
                  || $this->useRecoveryCode($user, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user->two_factor_secret         = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at   = null;
        $user->save();

        return redirect()->route('security.show')->with('success', '2FA has been disabled.');
    }

    /**
     * Regenerate recovery codes — requires the current TOTP code.
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user   = Auth::user();
        $secret = decrypt($user->two_factor_secret);

        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return view('security.recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'fresh'         => false,
        ]);
    }

    /**
     * Show the 2FA challenge page after login.
     */
    public function challenge()
    {
        if (!session()->has('two_factor.user_id')) {
            return redirect()->route('login');
        }

        return view('security.challenge');
    }

    /**
     * Verify the 2FA challenge code and complete login.
     */
    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = session('two_factor.user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user   = \App\Models\User::findOrFail($userId);
        $secret = decrypt($user->two_factor_secret);
        $code   = trim($request->code);

        $valid = $this->google2fa->verifyKey($secret, $code)
                 || $this->useRecoveryCode($user, $code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        session()->forget('two_factor.user_id');
        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('two_factor.verified', true);

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('User logged in (2FA verified)');

        return redirect()->intended(route('clubs.explore'));
    }

    /**
     * Change password for the authenticated user.
     * Invalidates all other active sessions on success.
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        // Invalidate all sessions except the current one.
        $currentSessionId = $request->session()->getId();
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Password changed — other sessions invalidated');

        return back()->with('success', 'Password changed successfully. All other devices have been signed out.');
    }

    // -------------------------------------------------------------------------

    private function buildQrCode(string $email, string $secret): string
    {
        $otpUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($otpUrl);
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn() =>
            strtoupper(Str::random(5)) . '-' . strtoupper(Str::random(5))
        )->all();
    }

    private function useRecoveryCode(\App\Models\User $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $index = array_search($code, $codes);

        if ($index === false) {
            return false;
        }

        // Burn the used recovery code so it cannot be reused.
        array_splice($codes, $index, 1);
        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->save();

        return true;
    }
}
