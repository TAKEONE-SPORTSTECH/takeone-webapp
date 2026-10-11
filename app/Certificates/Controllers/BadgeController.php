<?php

namespace App\Certificates\Controllers;

use App\Certificates\Support\CertificateBundles;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventBadges;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organiser's side of officials' badges (App\Events\Support\EventBadges): the list,
 * one badge as a picture or a PDF. The print-shop file (all of them, four to a sheet)
 * goes through ManageController::bundle like the certificates' — the same door.
 *
 * A badge is addressed by its random token, and every request re-asks whether this
 * user runs THAT badge's event; a token that is unknown or not theirs is a 404 either way.
 */
class BadgeController extends Controller
{
    use Concerns;

    private const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

    public function index(Request $request, string $key, EventBadges $badges, CertificateBundles $bundles): JsonResponse
    {
        [$provider, $event] = $this->managedContext(EventBadges::ALIAS, $key, $request);
        $locale = $badges->locale($event);

        $rows = $badges->officials($event)->map(function (EventOfficial $o) use ($badges, $locale) {
            $f = $badges->fields($o, $locale, false);
            $token = $badges->token($o);

            return [
                'token' => $token,
                'name' => $f['name'],
                'role' => $f['role'],
                'club' => $f['club'],
                'has_photo' => $f['photo'] !== null,
                'image_url' => route('me.badges.image', $token),
                'pdf_url' => route('me.badges.pdf', $token),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'badges' => $rows,
            'design_url' => route('me.certificates.design', ['context' => EventBadges::ALIAS, 'key' => $key]),
            'bundles' => $bundles->states($provider, $event),
        ]);
    }

    public function image(Request $request, string $token, EventBadges $badges)
    {
        $o = $this->official($token, $request, $badges);
        $png = $badges->render($o, $badges->locale($o->event), 150);

        return response($png, 200, self::HEADERS + ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline; filename="badge.png"']);
    }

    public function pdf(Request $request, string $token, EventBadges $badges)
    {
        $o = $this->official($token, $request, $badges);
        $name = \Illuminate\Support\Str::slug($o->user?->full_name ?: $o->user?->name ?: 'official') ?: 'official';

        return response($badges->render($o, $badges->locale($o->event)), 200, self::HEADERS + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="badge-'.$name.'.pdf"',
        ]);
    }

    private function official(string $token, Request $request, EventBadges $badges): EventOfficial
    {
        $o = EventOfficial::where('badge_token', $token)->with(['user', 'event'])->first();
        $user = $request->user();
        abort_unless($o && $o->user && $o->event && $user instanceof User && $badges->canManage($o->event, $user), 404);

        return $o;
    }
}
