<?php

namespace App\DevBoard\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The language TakeNote is read in — its own, not the platform's: friends from
 * anywhere use it, and TakeNote speaks more languages than the rest of TAKEONE
 * does yet (config devboard.locales).
 *
 * The choice made on this device (the `takenote_locale` cookie, set from the
 * language sheet) wins; then a language chosen for the account; then the
 * browser's own; then English. Only a language in the list is ever applied.
 */
class TakeNoteLocale
{
    public const COOKIE = 'takenote_locale';

    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(self::pick($request));

        return $next($request);
    }

    public static function pick(Request $request): string
    {
        $have = array_keys(config('devboard.locales', ['en' => []]));

        $chosen = (string) $request->cookie(self::COOKIE);
        if (in_array($chosen, $have, true)) {
            return $chosen;
        }

        // a language the person chose for their account (most have none: users.locale is NULL)
        $account = (string) $request->user()?->locale;
        if (in_array($account, $have, true)) {
            return $account;
        }

        // the browser's languages, best first: "pt-BR,pt;q=0.9" → pt_BR; "zh-Hans-CN" → zh
        foreach ($request->getLanguages() as $lang) {
            $lang = str_replace('-', '_', $lang);
            $base = strtolower(strtok($lang, '_'));
            if (in_array($lang, $have, true)) {
                return $lang;
            }
            if ($base === 'pt' && in_array('pt_BR', $have, true)) {
                return 'pt_BR';
            }
            if (in_array($base, $have, true)) {
                return $base;
            }
        }

        return 'en';
    }
}
