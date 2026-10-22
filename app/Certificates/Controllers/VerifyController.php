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
 * A face only where the member made their picture public, and bouts and the draw
 * only where the event's own pages would show them to the same reader
 * (EventCertificates::verifyStory).
 */
class VerifyController extends Controller
{
    public function __invoke(Request $request, string $token, Contexts $contexts)
    {
        $c = Engine::model()::query()->where('verify_token', $token)->where('status', '!=', 'draft')->first();
        $isMobile = (bool) $request->attributes->get('is_mobile');
        // The reader may switch language here (?lang=en|ar), whatever their account says.
        if (in_array($request->query('lang'), ['en', 'ar'], true)) {
            app()->setLocale($request->query('lang'));
        }
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
            // A club's thank-you is held by the club (EventCertificates::TYPE_CLUBS).
            'name' => $holder instanceof \Ahl\Certificates\Contracts\Recipient ? $holder->certificateName() : '',
            'title' => $c->title,
            'what' => $provider ? $provider->describe($c, $locale) : (string) $c->achievement,
            'place' => isset($details['place']) ? (int) $details['place'] : null,
            'club' => $details['club'] ?? null,
            'event' => $provider && $context ? $provider->headline($context) : null,
            'issuer' => $provider && $context ? $provider->issuer($context, $locale) : config('certificates.issuer'),
            'accent' => $provider && $context ? ($provider->brand($context)['accent'] ?? '#6d5bd0') : '#6d5bd0',
            'number' => $c->certificate_no,
            'issuedOn' => $c->issued_on?->locale($locale)->translatedFormat('j F Y'),
            'checkedAt' => now()->locale($locale)->translatedFormat('j F Y, H:i'),
            'shareUrl' => route('certificates.verify', $token),
            'lang' => $locale,
        ];

        // Beyond the paper: face, club, event, bouts, the draw — each under its own surface's rule.
        $viewer = $request->user();
        $view['story'] = $provider && method_exists($provider, 'verifyStory') ? $provider->verifyStory($c, $viewer instanceof User ? $viewer : null, $locale) : [];

        // The holder (and whoever runs the event) may download it and open their profile; a stranger reads the record.
        $isHolder = $viewer instanceof User && $holder instanceof User && $viewer->is($holder);
        $mayDownload = ! $c->isRevoked() && ($isHolder || ($viewer instanceof User && $provider && $context && $provider->canManage($context, $viewer)));
        $view['downloadUrl'] = $mayDownload ? route('me.certificates.pdf', $c->uuid) : null;
        $view['profileUrl'] = $isHolder ? route('member.show', $holder->uuid) : null;

        return response()
            ->view($isMobile ? 'cert::verify.mobile' : 'cert::verify.desktop', $view)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
