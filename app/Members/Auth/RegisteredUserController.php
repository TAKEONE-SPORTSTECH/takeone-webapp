<?php

namespace App\Members\Auth;

use App\Http\Controllers\Controller;
use App\Members\Mail\WelcomeEmail;
use App\Members\Models\User;
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
    /**
     * Platform registration page — its own URL, never a club flow.
     *   GET /register
     *
     * Old club QR/links used /register?intended=<club page>; those are redirected
     * to the dedicated club URL so existing printed codes keep working.
     */
    public function create(Request $request)
    {
        if ($request->filled('intended')
            && preg_match('~/mobile/([a-z]{2,3})/([^/?#]+)~i', (string) $request->input('intended'), $m)) {
            return redirect()->route('register.club', ['country' => strtolower($m[1]), 'slug' => $m[2]]);
        }

        // Platform registration never inherits a club context.
        $request->session()->forget(['club.context', 'url.intended']);

        return view('members::auth.register');
    }

    /**
     * Club registration wizard — its own URL, distinct from platform signup.
     *   GET /register/{country}/{slug}
     */
    public function createForClub(Request $request, string $country, string $slug)
    {
        $club = \App\Clubs\Models\Tenant::where('slug', $slug)->first([
            // `country`, not `country_code`: that is an accessor derived from it.
            'club_name', 'slug', 'country', 'logo', 'cover_image', 'currency',
            'enrollment_fee', 'registration_fee',
            'registration_splash_image', 'registration_terms', 'registration_requirements', 'translations',
        ]);

        // Unknown club → fall back to the platform registration page.
        if (! $club) {
            return redirect()->route('register');
        }

        $request->session()->forget(['club.context', 'url.intended']);
        // Send the verified member back to the club page afterwards.
        session(['url.intended' => \App\Http\Controllers\QrController::clubPageUrl($club)]);
        $this->setClubContext($club);

        return view('members::auth.register-wizard');
    }

    private function setClubContext(\App\Clubs\Models\Tenant $club): void
    {
        session(['club.context' => [
            'name' => $club->club_name,
            'logo' => $club->logo,
            'slug' => $club->slug,
            'cover_image' => $club->cover_image,
            'currency' => $club->currency,
            'enrollment_fee' => $club->enrollment_fee,
            'registration_fee' => $club->registration_fee,
            'splash' => $club->registration_splash_image,
            'terms' => $club->registration_terms,
            'terms_ar' => data_get($club->translations, 'registration_terms.ar'),
            'requirements' => $club->registration_requirements,
            'requirements_ar' => data_get($club->translations, 'registration_requirements.ar'),
        ]]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // New addresses are stored lower-cased and trimmed, and the uniqueness
        // check ignores case — `Ali@x.com` is the account `ali@x.com` holds.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => \App\Members\Support\UniqueEmail::normalise($request->input('email'))]);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new \App\Members\Support\UniqueEmail],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'mobile_number' => ['required', 'string', 'max:20', function (string $attribute, $value, \Closure $fail) use ($request) {
                /*
                 * duplicates.guard.registration: this phone already belongs to
                 * an account with NO email under a recognisably same name (an
                 * entry made for them at a desk or by a coach). Refused, never
                 * linked — adopting it on a typed phone would hand anybody's
                 * profile to whoever knows their number. Siblings on a parent's
                 * phone are not blocked: the name rule must agree too.
                 */
                if (! config('duplicates.guard.registration', false) || ! is_string($value)) {
                    return;
                }
                $taken = app(\App\Events\Support\KnownPerson::class)
                    ->matches((string) $request->input('country_code'), $value, (string) $request->input('full_name'))
                    ->contains(fn ($u) => blank($u->getRawOriginal('email')));
                if ($taken) {
                    $fail(__('An account with this phone number already exists. Please sign in instead — use "Forgot password" if you need to, or ask your club.'));
                }
            }],
            'gender' => ['required', 'in:Male,Female'],
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

            // Public registration NEVER grants super-admin (security fix,
            // 2026-10-03). It used to hand the role to whoever registered while
            // no super-admin existed — so losing the one super-admin account
            // (deleted, role removed) gave the whole platform to the next
            // stranger through the sign-up form. A super-admin is made only
            // from the server: `php artisan admin:make-super {email}` or
            // SuperAdminSeeder.

            // If the registrant came in through a club that has switched OFF email
            // verification (mail-service escape hatch), verify them on creation and
            // skip the verification email — they get straight in.
            $clubSlug = data_get(session('club.context'), 'slug');
            $club = $clubSlug ? \App\Clubs\Models\Tenant::where('slug', $clubSlug)->first() : null;
            $skipVerification = $club && ! $club->require_email_verification;

            if ($skipVerification) {
                $user->markEmailAsVerified();
            }
            // No `Registered` event here (2026-10-31): its only listener is
            // Laravel's SendEmailVerificationNotification, which queued a SECOND
            // WelcomeEmail (without the intended URL) beside the explicit one
            // below. The explicit mail is the one verification mail an account gets.

            // Log the user in
            Auth::login($user);

            // Send welcome email with verification link (skip when not verifying).
            if (! $skipVerification) {
                try {
                    $intended = session('url.intended');
                    Mail::to($user)->queue(new WelcomeEmail($user, $user, null, $intended));
                } catch (\Exception $e) {
                    // Log the error but don't stop the registration process
                    \Log::error('Failed to send welcome email: '.$e->getMessage());
                }
            }

            if ($skipVerification) {
                return redirect(session('url.intended') ?: route('clubs.explore'))
                    ->with('success', 'Registration successful! Welcome to TAKEONE.');
            }

            return redirect()->route('verification.notice')->with('success', 'Registration successful! Please check your email to verify your account.');
        } catch (\Exception $e) {
            \Log::error('Registration failed: '.$e->getMessage());

            return back()->withInput()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }
}
