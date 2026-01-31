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

        try {
            $user = User::create([
                'name' => $request->full_name,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'mobile' => ['code' => $request->country_code, 'number' => $request->mobile_number],
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'nationality' => $request->nationality,
            ]);

            // Assign super-admin role to the first registered user if no super-admin exists
            $hasSuperAdmin = User::whereHas('roles', function ($query) {
                $query->where('slug', 'super-admin');
            })->exists();

            if (!$hasSuperAdmin) {
                $user->assignRole('super-admin');
                // Refresh the user to load the role relationship
                $user->load('roles');
            }

            event(new Registered($user));

            // Log the user in
            Auth::login($user);

            // Send welcome email with verification link
            try {
                Mail::to($user->email)->send(new WelcomeEmail($user, $user, null));
            } catch (\Exception $e) {
                // Log the error but don't stop the registration process
                \Log::error('Failed to send welcome email: ' . $e->getMessage());
            }

            return redirect()->route('verification.notice')->with('success', 'Registration successful! Please check your email to verify your account.');
        } catch (\Exception $e) {
            \Log::error('Registration failed: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }
}
