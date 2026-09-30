<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\Certificates as Engine;
use App\Certificates\Support\Contexts;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * What the QR opens: is this certificate real, and what does it say?
 *
 * Public on purpose — a coach, a school or a federation checks a paper without
 * an account. So it says only what the paper already says (the name, what it
 * was for, who issued it, the number and the date) and the one thing paper
 * cannot: whether it still stands. A draft is never verifiable, and an unknown
 * token gets the same page as a draft, so a stranger learns nothing by guessing.
 * No face: this page is open to anybody holding the paper, and a name is enough.
 */
class VerifyController extends Controller
{
    public function __invoke(Request $request, string $token, Contexts $contexts)
    {
        $c = Engine::model()::query()->where('verify_token', $token)->where('status', '!=', 'draft')->first();
        $isMobile = (bool) $request->attributes->get('is_mobile');
        $locale = in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';

        if (! $c) {
            Log::info('certificates.verify.unknown', ['ip' => $request->ip()]);

            return response()->view($isMobile ? 'cert::verify.mobile' : 'cert::verify.desktop', ['found' => false], 404);
        }

        $context = $c->context_id ? $c->context : null;
        $provider = $contexts->forModel($context);
        $holder = $c->recipient;
        $details = (array) ($c->details ?? []);

        $view = [
            'found' => true,
            'revoked' => $c->isRevoked(),
            'revokedOn' => $c->revoked_at?->toDateString(),
            'name' => $holder instanceof User ? $holder->certificateName() : '',
            'title' => $c->title,
            'what' => $provider ? $provider->describe($c, $locale) : (string) $c->achievement,
            'place' => isset($details['place']) ? (int) $details['place'] : null,
            'club' => $details['club'] ?? null,
            'event' => $provider && $context ? $provider->headline($context) : null,
            'issuer' => $provider && $context ? $provider->issuer($context, $locale) : config('certificates.issuer'),
            'accent' => $provider && $context ? ($provider->brand($context)['accent'] ?? '#6d5bd0') : '#6d5bd0',
            'number' => $c->certificate_no,
            'issuedOn' => $c->issued_on?->toDateString(),
        ];

        return response()
            ->view($isMobile ? 'cert::verify.mobile' : 'cert::verify.desktop', $view)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
