<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\WelcomeEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'mobile_number' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:m,f'],
            'birthdate' => ['required', 'date', 'before:today'],
            'country_code' => ['required', 'string', 'max:10'],
            'nationality' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->full_name,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'mobile' => $request->country_code . $request->mobile_number,
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'nationality' => $request->nationality,
        ]);

        event(new Registered($user));

        // Send welcome email
        Mail::to($user->email)->send(new WelcomeEmail($user, $user, null));

        return redirect()->route('verification.notice')->with('success', 'Registration successful! Please check your email to verify your account.');
    }
}
