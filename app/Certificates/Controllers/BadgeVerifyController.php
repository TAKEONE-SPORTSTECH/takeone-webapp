<?php

namespace App\Certificates\Controllers;

use App\Events\Models\EventOfficial;
use App\Events\Support\EventBadges;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * What the QR on an official's badge opens: whether this person is an official at this
 * event, and as what. A steward at the door scans it; so may anybody holding the card.
 *
 * Keyed by the appointment's random badge token, never an id. An official who was
 * removed has no appointment left, so their old badge answers exactly as a code that
 * never existed — one reply for both, so a stranger learns nothing by guessing. No face
 * and no contact details: the name, the job, the club and the event are enough to check
 * a badge against the person wearing it.
 */
class BadgeVerifyController extends Controller
{
    public function __invoke(Request $request, string $token, EventBadges $badges)
    {
        $isMobile = (bool) $request->attributes->get('is_mobile');
        $locale = in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';
        $official = EventOfficial::where('badge_token', $token)->with(['user', 'event'])->first();
        $event = $official?->event;
        $headings = [
            'valid' => __('cert::messages.badge_verify_valid_title'),
            'unknown' => __('cert::messages.badge_verify_unknown_title'),
            'unknownHint' => __('cert::messages.badge_verify_unknown_hint'),
            'chip' => __('cert::messages.badge_verify_chip'),
            'note' => __('cert::messages.badge_verify_note'),
            'what' => __('cert::messages.badge_verify_role'),
        ];

        if (! $official || ! $official->user || ! $event || $event->is_archived) {
            Log::info('badges.verify.unknown', ['ip' => $request->ip()]);

            return response()->view($isMobile ? 'cert::verify.mobile' : 'cert::verify.desktop', ['found' => false, 'headings' => $headings], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
        }

        $f = $badges->fields($official, $locale, false);

        return response()->view($isMobile ? 'cert::verify.mobile' : 'cert::verify.desktop', [
            'found' => true,
            'revoked' => false,
            'revokedOn' => null,
            'name' => $f['name'],
            'title' => __('cert::messages.types.official_badge'),
            'what' => $f['role'],
            'place' => null,
            'club' => $f['club'],
            'event' => $badges->headline($event),
            'issuer' => $badges->issuer($event, $locale),
            'accent' => $badges->brand($event)['accent'] ?? '#6d5bd0',
            'number' => $f['certificate_no'],
            'issuedOn' => null,
            'headings' => $headings,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
