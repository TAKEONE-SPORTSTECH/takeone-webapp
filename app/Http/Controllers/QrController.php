<?php

namespace App\Http\Controllers;

use App\Models\ClubEvent;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Qr;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable QR posters for the four shareable surfaces:
 *   • a club's public page,
 *   • a club's registration link,
 *   • a member's profile,
 *   • an event (so people can open it and take part).
 *
 * QR images are rendered offline (App\Support\Qr → bacon). The same target URLs
 * are also encoded inline by the <x-qr-code> component (modal + downloads).
 */
class QrController extends Controller
{
    use HandlesClubAuthorization;

    /** Public URL of a club's page. */
    public static function clubPageUrl(Tenant $club): string
    {
        return route('clubs.show.public', ['country' => $club->country_code, 'slug' => $club->slug]);
    }

    /** Registration link that drops the new member straight into this club's flow. */
    public static function clubRegisterUrl(Tenant $club): string
    {
        return route('register', ['intended' => self::clubPageUrl($club)]);
    }

    /** A member's public profile (wall) URL. */
    public static function memberUrl(User $user): string
    {
        return $user->slug
            ? route('wall.show', $user)
            : route('wall.legacy', ['id' => $user->id]);
    }

    /** A member's management profile URL (admin/self view). */
    public static function memberManageUrl(User $user): string
    {
        return route('member.show', $user->uuid);
    }

    /**
     * QR target for a member: the public wall by default, or the management profile
     * when the caller asks for it (?target=manage) — used by the admin member popup.
     */
    private function memberQrTarget(Request $request, User $user): string
    {
        return $request->query('target') === 'manage'
            ? self::memberManageUrl($user)
            : self::memberUrl($user);
    }

    /** Event URL where a logged-in user can view it and take part. */
    public static function eventUrl(ClubEvent $event): string
    {
        return route('me.events.show', ['event' => $event->uuid]);
    }

    // ─────────────────────────── Posters ───────────────────────────

    public function clubPoster(Tenant $club): View
    {
        $this->authorizeClub($club);
        return $this->poster([
            'title'    => $club->tr('club_name') ?: $club->club_name,
            'subtitle' => $club->country,
            'kicker'   => 'Club page',
            'cta'      => 'Scan to view the club',
            'hint'     => 'See packages, schedule, gallery and more.',
            'logo'     => $club->logo ? asset('storage/' . $club->logo) : null,
            'logoIcon' => 'bi-buildings',
            'url'      => self::clubPageUrl($club),
        ]);
    }

    public function clubRegisterPoster(Tenant $club): View
    {
        $this->authorizeClub($club);
        return $this->poster([
            'title'    => $club->tr('club_name') ?: $club->club_name,
            'subtitle' => 'Join the club',
            'kicker'   => 'Register',
            'cta'      => 'Scan to register',
            'hint'     => 'Create your account and enrol in minutes.',
            'logo'     => $club->logo ? asset('storage/' . $club->logo) : null,
            'logoIcon' => 'bi-person-plus',
            'url'      => self::clubRegisterUrl($club),
        ]);
    }

    public function memberPoster(Request $request, User $user): View
    {
        abort_unless($this->canViewMember($request, $user), 403);
        return $this->poster([
            'title'    => $user->full_name ?: $user->name,
            'subtitle' => 'Member profile',
            'kicker'   => 'Profile',
            'cta'      => 'Scan to view profile',
            'hint'     => 'Connect and see their activity.',
            'logo'     => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
            'logoIcon' => 'bi-person',
            'url'      => $this->memberQrTarget($request, $user),
        ]);
    }

    /** Bare member QR as an inline SVG image (used by the member popup's QR view). */
    public function memberSvg(Request $request, User $user)
    {
        abort_unless(auth()->check(), 403);
        return response(Qr::svg($this->memberQrTarget($request, $user), 256, 1), 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function eventPoster(ClubEvent $event): View
    {
        abort_unless(auth()->check(), 403);
        $club = $event->tenant;
        return $this->poster([
            'title'    => $event->title,
            'subtitle' => $club?->club_name,
            'kicker'   => 'Event',
            'cta'      => 'Scan to take part',
            'hint'     => 'Open the event to register and join.',
            'logo'     => $club && $club->logo ? asset('storage/' . $club->logo) : null,
            'logoIcon' => 'bi-calendar-event',
            'url'      => self::eventUrl($event),
        ]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function poster(array $data): View
    {
        $data['svg'] = Qr::svg($data['url'], 600, 1);
        return view('qr.poster', $data);
    }

    /** Self, super-admin, or a club admin of a club this member belongs to. */
    private function canViewMember(Request $request, User $user): bool
    {
        $me = auth()->user();
        if (! $me) return false;
        if ($me->id === $user->id) return true;
        if ($me->isSuperAdmin()) return true;

        // A manager may pull the QR for a member of a club they run.
        if ($request->filled('club')) {
            $club = is_numeric($request->club)
                ? Tenant::find($request->club)
                : Tenant::where('slug', $request->club)->first();
            if ($club && $this->canManageClub($club)
                && Membership::where('tenant_id', $club->id)->where('user_id', $user->id)->exists()) {
                return true;
            }
        }
        return false;
    }
}
