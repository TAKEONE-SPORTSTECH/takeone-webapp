<?php

namespace App\Http\Controllers;

use App\Models\ActivityCatalog;
use Illuminate\Http\Request;

/**
 * Read-only viewer for a global-directory activity — renders the rich,
 * bilingual write-up (and hero image, once one exists). PUBLIC: general
 * sport/knowledge content with no tenant/personal data, so no auth is required;
 * bound by a non-guessable uuid and throttled at the route. Guests are prompted
 * to sign in on the page itself.
 */
class ActivityCatalogController extends Controller
{
    public function show(Request $request, ActivityCatalog $activity)
    {
        abort_unless($activity->is_active, 404);

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');
        $isMobile = (bool) $request->attributes->get('is_mobile', false);

        // Per-language content map. Only languages that actually have a
        // description are offered, so the on-page EN/AR toggle never exposes an
        // empty tab. The base language always lives in the plain columns.
        $content = [];
        foreach (config('locales', []) as $code => $meta) {
            $name = $code === $fallback ? $activity->name : data_get($activity->translations, "name.{$code}");
            $desc = $code === $fallback ? $activity->description : data_get($activity->translations, "description.{$code}");
            if (filled($desc)) {
                $content[$code] = [
                    'name' => $name ?: $activity->name,
                    'description' => $desc,
                    'dir' => $meta['dir'] ?? 'ltr',
                    'native' => $meta['native'] ?? strtoupper($code),
                ];
            }
        }

        // Always have at least the base content (activity with no description yet).
        if (empty($content)) {
            $content[$fallback] = [
                'name' => $activity->name,
                'description' => (string) $activity->description,
                'dir' => config("locales.{$fallback}.dir", 'ltr'),
                'native' => config("locales.{$fallback}.native", strtoupper($fallback)),
            ];
        }

        // Obey the viewer's language preference; fall back to base, then whatever exists.
        $defaultLang = isset($content[$locale]) ? $locale
            : (isset($content[$fallback]) ? $fallback : array_key_first($content));

        $default = $content[$defaultLang];

        return view($isMobile ? 'activities.mobile.show' : 'activities.desktop.show', [
            'activity' => $activity,
            'name' => $default['name'],
            'description' => $default['description'],
            'locale' => $locale,
            'content' => $content,
            'defaultLang' => $defaultLang,
        ]);
    }
}
