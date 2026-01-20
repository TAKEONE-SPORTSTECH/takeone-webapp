<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
            $value = $request->email;
        } else {
            $field = 'mobile';
            $value = trim(preg_replace('/[^\d\+]/', '', $request->email)); // Keep digits and +, trim
        }
        $credentials = [$field => $value, 'password' => $request->password];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            if (!$request->user()->hasVerifiedEmail()) {
                Auth::logout();
                return redirect()->route('verification.notice')->withErrors([
                    'email' => 'You need to verify your email address before logging in.',
                ]);
            }

            return redirect()->route('family.dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
