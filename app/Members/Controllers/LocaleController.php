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
        $available = array_keys(config('locales', []));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($available)],
        ]);

        $request->session()->put('locale', $data['locale']);

        if ($user = $request->user()) {
            $user->update(['locale' => $data['locale']]);
        }

        app()->setLocale($data['locale']);

        // A browser, not a script. Send it back to the page it was on — but
        // only if that page is OURS: the Referer is attacker-controllable, so
        // echoing it into a redirect unchecked is an open redirect.
        if (! $request->expectsJson()) {
            $back = $request->headers->get('referer');
            $same = $back && parse_url($back, PHP_URL_HOST) === $request->getHost();

            return redirect()->to($same ? $back : '/');
        }

        return response()->json([
            'success' => true,
            'message' => __('shared.language_updated'),
            'locale' => $data['locale'],
            'dir' => config('locales.'.$data['locale'].'.dir', 'ltr'),
        ]);
    }
}
