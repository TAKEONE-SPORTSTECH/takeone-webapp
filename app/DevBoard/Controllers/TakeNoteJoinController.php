<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Support\Notebooks;
use App\Http\Controllers\Controller;
use App\Members\Mail\WelcomeEmail;
use App\Members\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * An invite link (owner, 28 Oct 2026: "provide them a link and they can
 * register, and start using the note app" → "let them register straight away").
 *
 * Opening the link: signed in → the link becomes their notebook and they are
 * in. Signed out → TakeNote's own door, in their language: a name, an email,
 * a password, and they are writing — no waiting for a confirmation email
 * (Middleware\VerifiedOrLinkFriend opens TakeNote, and only TakeNote, for
 * them). The account is a normal TAKEONE account and gets the normal
 * confirmation email for everything else. "I already have an account" signs
 * in through the platform's login and comes back here.
 *
 * A link works once. Used, removed or never real all answer alike.
 */
class TakeNoteJoinController extends Controller
{
    public function show(Request $request, string $token)
    {
        $book = Notebooks::findLink($token);
        $user = $request->user();

        // somebody who already has TakeNote goes in, and the link stays for whoever it was meant for
        if ($user && Notebooks::for($user)) {
            return redirect()->route('takenote.app');
        }
        if (! $book) {
            return $this->door('gone', null, null, 404);
        }
        if ($user) {
            return Notebooks::claimLink($book, $user) ? redirect()->route('takenote.app') : $this->door('gone', null, null, 404);
        }

        // "I already have an account": the platform's login sends them back here
        $request->session()->put('url.intended', route('takenote.join', $token));

        return $this->door('open', $book->invited_by, $token);
    }

    public function store(Request $request, string $token)
    {
        if ($request->user()) {
            return redirect()->route('takenote.join', $token);
        }
        $book = Notebooks::findLink($token);
        if (! $book) {
            return redirect()->route('takenote.join', $token);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = DB::transaction(function () use ($data, $book) {
                $user = User::create([
                    'name' => $data['full_name'],
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);
                // somebody else opened the same link a moment earlier: no account is left behind
                if (! Notebooks::claimLink($book, $user)) {
                    throw new \DomainException('link gone'); // not a RuntimeException: a real database error must still surface
                }

                return $user;
            });
        } catch (\DomainException $e) {
            return redirect()->route('takenote.join', $token);
        }

        $this->confirmEmail($user); // the usual confirmation email — for the rest of TAKEONE; it comes back to TakeNote
        Auth::login($user, true);   // stays signed in on this device
        $request->session()->regenerate();

        return redirect()->route('takenote.app');
    }

    /**
     * TakeNote's own sign-up, from its sign-in door ("New here?") — for a friend invited by EMAIL, who
     * must confirm that address (it is what the invitation trusts). Unlike the platform's /register, the
     * confirmation link brings them back to TakeNote, wherever they open it (the owner, 28 Oct 2026:
     * "after she registered it took her to the TAKEONE page — it must take her to TakeNote").
     */
    public function signupForm(Request $request)
    {
        $user = $request->user();
        if ($user && Notebooks::for($user)) {
            return redirect()->route('takenote.app');
        }
        if ($user) {
            return $user->hasVerifiedEmail()
                ? $this->door('noaccess', null, null, 403)
                : $this->door('check', null, null, 200, $user->email);
        }

        return $this->door('signup', null, null);
    }

    public function signup(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('takenote.signup');
        }
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $this->confirmEmail($user);
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('url.intended', route('takenote.app')); // confirming in THIS browser lands in TakeNote too

        return redirect()->route('takenote.signup'); // "check your email", in TakeNote's own door
    }

    /** "Send it again" on TakeNote's own "check your email" door — the new link comes back to TakeNote too. */
    public function resend(Request $request)
    {
        $user = $request->user();
        if (! $user || $user->hasVerifiedEmail()) {
            return redirect()->route('takenote.app');
        }
        $this->confirmEmail($user);

        return redirect()->route('takenote.signup')->with('resent', true);
    }

    /**
     * Signing out of TakeNote: the platform's own sign-out (same log line, same session wipe), but it
     * lands on TakeNote's sign-in door rather than TAKEONE's — they came for the notes.
     */
    public function signOut(Request $request)
    {
        if (Auth::check()) {
            activity('auth')->causedBy(Auth::user())->withProperties(['ip' => $request->ip(), 'from' => 'takenote'])->log('User logged out');
            Auth::guard('web')->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('takenote.app');
    }

    /** The platform's own confirmation email, with TakeNote as where its link lands (the link is signed). */
    private function confirmEmail(User $user): void
    {
        rescue(fn () => Mail::to($user)->queue(new WelcomeEmail($user, $user, null, route('takenote.app'))), report: true);
    }

    private function door(string $state, ?int $inviterId, ?string $token, int $status = 200, ?string $email = null)
    {
        $inviter = $inviterId ? User::find($inviterId, ['id', 'full_name']) : null;
        $name = $inviter ? (trim(explode(' ', trim((string) $inviter->full_name))[0] ?? '') ?: $inviter->full_name) : null;

        return response()->view('devboard::takenote.join', ['state' => $state, 'inviter' => $name, 'token' => $token, 'email' => $email], $status)
            ->header('Cache-Control', 'no-store');
    }
}
