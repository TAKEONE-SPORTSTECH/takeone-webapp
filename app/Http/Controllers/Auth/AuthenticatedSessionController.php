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
            $credentials = ['email' => $request->email, 'password' => $request->password];
        } else {
            // Treat as mobile number
            $cleanInput = preg_replace('/[^\d]/', '', $request->email);
            $user = \App\Models\User::whereRaw("json_extract(mobile, '$.number') = ?", [$cleanInput])
                ->orWhereRaw("json_extract(mobile, '$.code') || json_extract(mobile, '$.number') = ?", [$request->email])
                ->first();
            if ($user) {
                $credentials = ['email' => $user->email, 'password' => $request->password];
            } else {
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // Temporarily disable email verification for testing
            // if (!$request->user()->hasVerifiedEmail()) {
            //     Auth::logout();
            //     return redirect()->route('verification.notice')->withErrors([
            //         'email' => 'You need to verify your email address before logging in.',
            //     ]);
            // }

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
