<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticatedSessionController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const LOCKOUT_SECS  = 15 * 60; // 15 minutes

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email'    => ['required'],
            'password' => ['required'],
        ]);

        $lockKey = 'login.lockout.' . strtolower($request->input('email')) . '|' . $request->ip();

        // Check lockout before attempting auth.
        if (Cache::has($lockKey)) {
            $seconds = Cache::get($lockKey . '.ttl', self::LOCKOUT_SECS);
            $minutes = (int) ceil($seconds / 60);
            return back()->withErrors([
                'email' => "Too many failed login attempts. Try again in {$minutes} minute(s).",
            ])->onlyInput('email');
        }

        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => $request->email, 'password' => $request->password];
        } else {
            $cleanInput = preg_replace('/[^\d]/', '', $request->email);
            $user = \App\Models\User::whereRaw("json_extract(mobile, '$.number') = ?", [$cleanInput])
                ->orWhereRaw("json_extract(mobile, '$.code') || json_extract(mobile, '$.number') = ?", [$request->email])
                ->first();
            if ($user) {
                $credentials = ['email' => $user->email, 'password' => $request->password];
            } else {
                $this->incrementFailures($lockKey);
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }
        }

        if (Auth::attempt($credentials)) {
            // Clear any lockout state on successful login.
            Cache::forget($lockKey);
            Cache::forget($lockKey . '.attempts');

            $request->session()->regenerate();
            $authedUser = $request->user();

            if (!$authedUser->hasVerifiedEmail()) {
                Auth::logout();
                return redirect()->route('verification.notice')->withErrors([
                    'email' => 'You need to verify your email address before logging in.',
                ]);
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

            return redirect()->intended(route('clubs.explore'));
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
        $attemptsKey = $lockKey . '.attempts';
        $attempts    = (int) Cache::get($attemptsKey, 0) + 1;

        Cache::put($attemptsKey, $attempts, self::LOCKOUT_SECS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put($lockKey, true, self::LOCKOUT_SECS);
            Cache::put($lockKey . '.ttl', self::LOCKOUT_SECS, self::LOCKOUT_SECS);
        }

        return $attempts;
    }
}
