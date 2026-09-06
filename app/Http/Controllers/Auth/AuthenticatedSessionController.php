<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticatedSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECS = 15 * 60; // 15 minutes

    public function create()
    {
        $isMobile = request()->attributes->get('is_mobile', false);

        return view($isMobile ? 'auth.mobile.login' : 'auth.desktop.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        $lockKey = 'login.lockout.'.strtolower($request->input('email')).'|'.$request->ip();

        // Check lockout before attempting auth.
        if (Cache::has($lockKey)) {
            $seconds = Cache::get($lockKey.'.ttl', self::LOCKOUT_SECS);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors([
                'email' => "Too many failed login attempts. Try again in {$minutes} minute(s).",
            ])->onlyInput('email');
        }

        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => $request->email, 'password' => $request->password];
        } else {
            /*
             * Signing in by telephone number.
             *
             * Three things were wrong with the way this used to work, and all
             * three are the reason it is written out at length here:
             *
             *  1. It matched `json_extract(mobile, '$.number')` against the
             *     raw digits, so the number had to be typed EXACTLY as stored.
             *     A space, a leading zero or a different way of writing the
             *     country code and the same person was a stranger. It now
             *     matches the normalised `phone_key` column.
             *
             *  2. Having found the user it authenticated with `['email' => …]`
             *     — so an account with NO email address could never sign in at
             *     all. That is precisely the account the public entry door
             *     creates when somebody joins a competition by phone. It now
             *     authenticates by id.
             *
             *  3. It took `->first()`. A telephone belongs to a household, not
             *     to a person: a parent enters two children on one number, and
             *     this platform models guardians and dependents because that is
             *     the normal case. `first()` silently signed in whichever row
             *     the database happened to return — the wrong child, with no
             *     way to tell. Every account on the number is now considered,
             *     and if more than one answers to the password we ASK.
             */
            $candidates = \App\Members\Models\User::withPhone($request->email)
                ->whereNotNull('password')
                // A ceiling, because each candidate costs a bcrypt comparison
                // and the number is not ours to trust.
                ->limit(5)
                ->get();

            $matches = $candidates->filter(
                fn ($u) => \Illuminate\Support\Facades\Hash::check($request->password, $u->password)
            )->values();

            // More than one person on this number uses this password — a
            // parent and child sharing both, most likely. The password is
            // already proven; what is missing is WHO. Ask, rather than guess.
            if ($matches->count() > 1) {
                Cache::forget($lockKey);
                Cache::forget($lockKey.'.attempts');

                $request->session()->put('login.choose', [
                    'ids' => $matches->pluck('id')->all(),
                    'at' => now()->timestamp,
                ]);

                return redirect()->route('login.choose');
            }

            if ($matches->count() !== 1) {
                $this->incrementFailures($lockKey);

                // The SAME sentence a wrong password gets. Saying "no such
                // number" would tell a stranger which numbers are real.
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }

            // By id, not by email — the whole point is that this account may
            // not have one. `attempt()` rather than `loginUsingId()` so the
            // usual events, throttles and password rehashing still happen.
            $credentials = ['id' => $matches->first()->id, 'password' => $request->password];
        }

        // Always "remember" the user so they stay logged in across browser
        // restarts and session expiry — re-authenticated silently via the
        // long-lived remember-me cookie until they explicitly log out.
        if (Auth::attempt($credentials, true)) {
            // Clear any lockout state on successful login.
            Cache::forget($lockKey);
            Cache::forget($lockKey.'.attempts');

            $request->session()->regenerate();
            $authedUser = $request->user();

            // Only meaningful for an account that HAS an email. A competitor
            // who joined by telephone has nothing to verify, and bouncing them
            // here would lock them out of the event they just entered.
            if ($authedUser->email && ! $authedUser->hasVerifiedEmail()) {
                $email = $authedUser->email;
                Auth::logout();

                return redirect()->route('login')
                    ->with('warning', 'Your email address is not verified. Please check your inbox and click the verification link.')
                    ->with('unverified_email', $email);
            }

            if ($authedUser->hasTwoFactorEnabled()) {
                $userId = $authedUser->id;
                Auth::logout();
                $request->session()->put('two_factor.user_id', $userId);

                return redirect()->route('two-factor.challenge');
            }

            activity('auth')
                ->causedBy($authedUser)
                ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
                ->log('User logged in');

            $request->session()->put('two_factor.verified', true);

            return redirect()->intended(\App\Support\Landing::url($request));
        }

        // Failed attempt — increment counter and maybe lock.
        $attempts = $this->incrementFailures($lockKey);

        activity('auth')
            ->withProperties(['ip' => $request->ip(), 'email' => $request->input('email')])
            ->log('Failed login attempt');

        $remaining = self::MAX_ATTEMPTS - $attempts;

        if ($remaining <= 0) {
            return back()->withErrors([
                'email' => 'Too many failed login attempts. Your account is locked for 15 minutes.',
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => "The provided credentials do not match our records. {$remaining} attempt(s) remaining before lockout.",
        ])->onlyInput('email');
    }

    /* ==================== "Who is signing in?" ==================== */

    /**
     * Several people share this telephone number AND this password.
     *
     * Reached only from `store()`, and only once the password has already been
     * checked against every candidate. The session holds the shortlist — ids
     * the SERVER chose — so a caller cannot nominate an account of their own;
     * arriving here with no shortlist, or a stale one, is sent back to the
     * sign-in form with nothing revealed.
     */
    public function choose(Request $request)
    {
        $ids = $this->shortlist($request);

        if ($ids === null) {
            return redirect()->route('login');
        }

        $people = \App\Members\Models\User::whereIn('id', $ids)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'gender', 'updated_at']);

        // One left (an account was removed between the two steps) — no
        // question worth asking.
        if ($people->count() === 1) {
            return $this->finishChosen($request, $people->first());
        }

        $isMobile = $request->attributes->get('is_mobile', false);

        return view($isMobile ? 'auth.mobile.choose' : 'auth.desktop.choose', ['people' => $people]);
    }

    /** They picked one. */
    public function chose(Request $request)
    {
        $ids = $this->shortlist($request);

        if ($ids === null) {
            return redirect()->route('login');
        }

        $data = $request->validate(['user' => ['required', 'integer']]);

        // The chosen id must be one the SERVER put on the shortlist. Without
        // this the whole step would be a login form with no password.
        if (! in_array((int) $data['user'], $ids, true)) {
            return redirect()->route('login');
        }

        $person = \App\Members\Models\User::find((int) $data['user']);

        if (! $person) {
            return redirect()->route('login');
        }

        return $this->finishChosen($request, $person);
    }

    /**
     * The server-chosen shortlist, or null if there is not a fresh one.
     *
     * Five minutes: long enough to read a couple of names, short enough that a
     * shared or abandoned browser is not a way in.
     */
    private function shortlist(Request $request): ?array
    {
        $pending = $request->session()->get('login.choose');

        if (! is_array($pending) || empty($pending['ids']) || ! is_array($pending['ids'])) {
            return null;
        }

        if (now()->timestamp - (int) ($pending['at'] ?? 0) > 300) {
            $request->session()->forget('login.choose');

            return null;
        }

        return array_map('intval', $pending['ids']);
    }

    /** Sign the chosen person in, through the same gates `store()` uses. */
    private function finishChosen(Request $request, \App\Members\Models\User $person)
    {
        $request->session()->forget('login.choose');

        Auth::loginUsingId($person->id, true);
        $request->session()->regenerate();

        if ($person->email && ! $person->hasVerifiedEmail()) {
            $email = $person->email;
            Auth::logout();

            return redirect()->route('login')
                ->with('warning', 'Your email address is not verified. Please check your inbox and click the verification link.')
                ->with('unverified_email', $email);
        }

        if ($person->hasTwoFactorEnabled()) {
            Auth::logout();
            $request->session()->put('two_factor.user_id', $person->id);

            return redirect()->route('two-factor.challenge');
        }

        activity('auth')
            ->causedBy($person)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'via' => 'phone+choice'])
            ->log('User logged in');

        $request->session()->put('two_factor.verified', true);

        return redirect()->intended(\App\Support\Landing::url($request));
    }

    public function destroy(Request $request)
    {
        activity('auth')
            ->causedBy(Auth::user())
            ->withProperties(['ip' => $request->ip()])
            ->log('User logged out');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function incrementFailures(string $lockKey): int
    {
        $attemptsKey = $lockKey.'.attempts';
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        Cache::put($attemptsKey, $attempts, self::LOCKOUT_SECS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put($lockKey, true, self::LOCKOUT_SECS);
            Cache::put($lockKey.'.ttl', self::LOCKOUT_SECS, self::LOCKOUT_SECS);
        }

        return $attempts;
    }
}
