<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the active UI language. Persists to the authenticated user (so it
     * follows them across devices) and to the session. The client reloads after
     * a success so the new <html dir>/font apply globally — the mobile shell
     * swaps content via AJAX and never re-renders the root element otherwise.
     */
    public function update(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => __('shared.language_updated'),
            'locale'  => $data['locale'],
            'dir'     => config('locales.' . $data['locale'] . '.dir', 'ltr'),
        ]);
    }
}
