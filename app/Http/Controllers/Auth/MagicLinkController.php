<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLoginLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MagicLinkController extends Controller
{
    /**
     * Email a one-time, signed login link to the address provided.
     *
     * Always reports success regardless of whether the email exists, so the
     * endpoint can't be used to enumerate registered accounts.
     */
    public function send(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            try {
                Mail::to($user->email)->queue(new MagicLoginLink($user, $this->safeIntended($request->input('intended'))));
            } catch (\Throwable $e) {
                \Log::error('Magic login link send failed: ' . $e->getMessage());
            }
        }

        return back()->with('magic_sent', $request->email);
    }

    /**
     * Consume a signed login link: verify the signature, log the user in with a
     * long-lived "remember" session, mark their email verified, and redirect.
     *
     * The route is protected by the `signed` middleware, so a tampered or
     * expired URL never reaches this method.
     */
    public function login(Request $request, User $user)
    {
        // Clicking a link delivered to their inbox proves email ownership.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, true); // remember = true → session persists until logout
        $request->session()->regenerate();

        return redirect()->to($this->safeIntended($request->query('intended')) ?: route('clubs.explore'));
    }

    /**
     * Return the intended URL only if it is safe to redirect to — i.e. a relative
     * path on this site, or an absolute URL on this exact host. Everything else
     * (cross-origin, protocol-relative `//evil.com`, backslash tricks `/\evil`,
     * non-http schemes) is rejected to prevent open redirects. Returns null when
     * unsafe so callers fall back to a known-good default.
     */
    private function safeIntended(?string $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        // Relative path: a single leading "/" not followed by "/" or "\".
        if (preg_match('#^/(?![/\\\\])#', $intended)) {
            return $intended;
        }

        // Absolute URL, but only on this exact host over http(s).
        $parts = parse_url($intended);
        if (($parts['host'] ?? null) === parse_url(config('app.url'), PHP_URL_HOST)
            && in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return $intended;
        }

        return null;
    }
}
