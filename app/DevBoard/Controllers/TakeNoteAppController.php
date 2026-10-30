<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Support\BoardAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * TakeNote as an installable app: the one page the phone opens, its manifest
 * and its service worker. The app's scope is /me/takenote and what is under
 * it, nothing else on the site — the worker cannot touch any other page.
 *
 * The page is reachable signed out on purpose: a person opening the app
 * signed out sees TakeNote's own sign-in (which posts to the platform's
 * normal login, with its throttle, lockout, verification and two-factor),
 * not the website's. The page carries no notes: everything it shows comes
 * from me.takenote.data, behind the full member stack and EnsureBoardAccess.
 */
class TakeNoteAppController extends Controller
{
    public function app(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            // Back here after signing in, wherever the login form sends people.
            $request->session()->put('url.intended', route('takenote.app'));

            return response()->view('devboard::takenote.sign-in')->header('Cache-Control', 'no-store');
        }

        // signed in without TakeNote: waiting on their email confirmation, or not invited — TakeNote's own
        // door says which, instead of a 404 that bounces them to the TAKEONE home page
        if (! BoardAccess::allows($user)) {
            abort_unless(\Illuminate\Support\Facades\Route::has('takenote.signup'), 404); // friends off: as before
            return redirect()->route('takenote.signup');
        }

        // a link to a folder shared with them (the notice they were sent): open it, then a clean address
        $space = $request->query('space');
        if (is_string($space) && \App\DevBoard\Support\Notebooks::sharingOn()) {
            if (\App\DevBoard\Support\Notebooks::shareFor($user, $space)) {
                $request->session()->put(\App\DevBoard\Support\Notebooks::SPACE, $space);
            }

            return redirect()->route('takenote.app');
        }

        return response()->view('devboard::takenote.app')->header('Cache-Control', 'no-store');
    }

    public function manifest(): Response
    {
        $icon = fn (string $file, string $size, string $purpose = 'any') => [
            'src' => asset('images/takenote/'.$file), 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose,
        ];

        $manifest = [
            'id' => route('takenote.app', [], false),
            'name' => 'TakeNote',
            'short_name' => 'TakeNote',
            'description' => __('devboard::messages.tn_tagline'),
            'start_url' => route('takenote.app', [], false).'?source=app',
            // The app's own address and everything under it — nothing else on the site.
            'scope' => route('takenote.app', [], false),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#160f24',
            'theme_color' => '#160f24',
            'lang' => app()->getLocale(),
            'icons' => [
                $icon('icon-192.png', '192x192'),
                $icon('icon-512.png', '512x512'),
                $icon('icon-maskable-512.png', '512x512', 'maskable'),
            ],
            'shortcuts' => [
                ['name' => __('devboard::messages.new_note'), 'url' => route('takenote.app', [], false).'?new=write',
                    'icons' => [['src' => asset('images/takenote/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']]],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function worker(): Response
    {
        return response()->view('devboard::takenote.worker', [], 200)
            ->header('Content-Type', 'text/javascript; charset=utf-8')
            // The worker lives in /me/takenote/ but must also cover the app page,
            // /me/takenote itself — widen it by exactly that, no further.
            ->header('Service-Worker-Allowed', route('takenote.app', [], false))
            ->header('Cache-Control', 'no-cache');
    }
}
