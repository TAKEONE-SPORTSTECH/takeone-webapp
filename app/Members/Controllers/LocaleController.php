<?php

namespace App\Members\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the active UI language. Persists to the authenticated user (so it
     * follows them across devices) and to the session. The client reloads after
     * a success so the new <html dir>/font apply globally — the mobile shell
     * swaps content via AJAX and never re-renders the root element otherwise.
     *
     * TWO callers, and they want different answers:
     *
     *   · The member settings screens fetch() this with `Accept:
     *     application/json` and patch themselves — they get the JSON they
     *     always got, unchanged.
     *   · The PUBLIC event cover (`locale.set`) posts a plain <form>, because a
     *     visitor with no account must be able to choose a language with no JS
     *     at all. Handing that browser a JSON document would put raw braces on
     *     the screen, so a non-JSON request is sent back where it came from and
     *     the page simply re-renders in the new language.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        /*
         * Both lists. config/locales.php is what the INTERFACE speaks;
         * config/content_locales.php is every language an organiser's own words
         * can be READ in (App\Translation). A visitor picking Turkish on an
         * event cover is choosing the second kind, and refusing it here — which
         * this did until 2026-09-08 — silently failed validation, redirected
         * back, and left the page in English with the translation sitting
         * unread in the database.
         */
        $available = array_keys(config('locales', []) + config('content_locales', []));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($available)],
            // Where to land afterwards, when the caller knows better than the
            // Referer does — see the redirect below.
            'back' => ['nullable', 'string', 'max:2048'],
            /*
             * The event this choice belongs to, when it belongs to one.
             *
             * Present → the language is remembered FOR THAT EVENT and nothing
             * else changes. Absent → it is the member's own platform-wide
             * preference, exactly as before.
             */
            'event' => ['nullable', 'string', 'uuid'],
        ]);

        if (! empty($data['event'])) {
            /*
             * ⚠️ Scoped, and deliberately narrow: no `session('locale')`, no
             * `users.locale`. A visitor reading one competition in Portuguese
             * is not asking for a Portuguese platform, and an organiser whose
             * own admin panel silently changed language because they previewed
             * their poster would rightly call that a bug. It was one.
             */
            \App\Translation\EventLocale::set($request, $data['event'], $data['locale']);
        } else {
            $request->session()->put('locale', $data['locale']);

            if ($user = $request->user()) {
                $user->update(['locale' => $data['locale']]);
            }
        }

        app()->setLocale($data['locale']);

        // A browser, not a script. Send it back to the page it was on — but
        // only if that page is OURS: the Referer is attacker-controllable, so
        // echoing it into a redirect unchecked is an open redirect.
        if (! $request->expectsJson()) {
            /*
             * An explicit destination beats the Referer.
             *
             * The white-label event surface is a self-contained app: nothing on
             * it may land the reader on the platform. Switching language there
             * used to rely on the Referer alone, and a browser that sends none
             * — a privacy setting, an in-app WebView, or any future
             * Referrer-Policy — fell through to `/`, i.e. straight out of the
             * event. So the caller may name where it wants to come back to.
             *
             * Held to a PATH on our own host, exactly as tightly as the Referer
             * is: an absolute URL elsewhere, a protocol-relative `//host` or a
             * `javascript:` are all refused rather than sanitised, because a
             * redirect target is the one input where "nearly safe" is unsafe.
             */
            $wanted = $data['back'] ?? null;

            /*
             * 303, not 302.
             *
             * This is a POST that changed state, redirecting to a page that
             * only answers GET. On a 302 the method is formally UNCHANGED —
             * browsers convert it to GET for historical reasons, but `curl -L`
             * re-posts and gets a 405, and a reload or a back-navigation onto
             * the redirect can re-submit the form. 303 See Other says "go and
             * GET that instead", which is exactly what is meant, and it leaves
             * nothing re-submittable in the history.
             */
            if ($wanted !== null && str_starts_with($wanted, '/') && ! str_starts_with($wanted, '//')) {
                return redirect()->to($wanted, 303);
            }

            $back = $request->headers->get('referer');
            $same = $back && parse_url($back, PHP_URL_HOST) === $request->getHost();

            return redirect()->to($same ? $back : '/', 303);
        }

        return response()->json([
            'success' => true,
            'message' => __('shared.language_updated'),
            'locale' => $data['locale'],
            'dir' => config('locales.'.$data['locale'].'.dir')
                ?? config('content_locales.'.$data['locale'].'.dir', 'ltr'),
        ]);
    }
}
