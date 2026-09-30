<?php

namespace App\Clubs\Controllers;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubLock;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /club — the manager's front door to the club admin panel.
 *
 * It decides nothing about access: the panel's own authorizeClub() still runs
 * on every page behind it. This only answers "which club do you mean?":
 * one → straight to its dashboard, several → a picker, none → back home.
 *
 * The set of clubs listed follows HandlesClubAuthorization exactly (owner,
 * club-admin role on that club, owner of the club's approved chain). A
 * super-admin, who may open every club, is listed only their own and offered
 * the platform's club list, so the page never dumps the whole tenant table.
 *
 * It also engages club mode (App\Clubs\Support\ClubLock): from here on the
 * session stays inside these clubs until the manager signs out.
 */
class ClubEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $clubs = Tenant::query()
            ->where(fn (Builder $q) => $this->managedBy($q, $user->id))
            ->withCount('members')
            ->orderBy('club_name')
            ->get(['id', 'club_name', 'slug', 'logo', 'country', 'status', 'owner_user_id', 'business_id', 'translations']);

        $isSuperAdmin = $user->isSuperAdmin();
        $chainIds = $this->chains($user->id)->pluck('id')->all();

        // Coming in through this door locks the session into these clubs until
        // sign-out (never a super-admin — see ClubLock).
        ClubLock::engage($user, $clubs);

        if ($clubs->count() === 1 && ! $isSuperAdmin) {
            return redirect()->route('admin.club.dashboard', $clubs->first()->slug);
        }

        if ($clubs->isEmpty() && ! $isSuperAdmin) {
            return redirect('/')->with('error', __('clubs::club.entry_none'));
        }

        $rows = $clubs->map(fn (Tenant $c) => [
            'name' => $c->club_name,
            'slug' => $c->slug,
            'logo' => $c->logo,
            'country' => $c->country,
            'members' => (int) $c->members_count,
            'pending' => $c->status === 'pending',
            'role' => match (true) {
                (int) $c->owner_user_id === (int) $user->id => 'owner',
                in_array($c->business_id, $chainIds, false) => 'chain',
                default => 'admin',
            },
        ]);

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'clubs::entry.mobile' : 'clubs::entry.desktop', [
            'clubs' => $rows,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /** Same three grants as HandlesClubAuthorization::canManageClub(), as a query. */
    private function managedBy(Builder $q, int $userId): void
    {
        $q->where('owner_user_id', $userId)
            ->orWhereIn('id', DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $userId)
                ->where('roles.slug', 'club-admin')
                ->whereNotNull('user_roles.tenant_id')
                ->select('user_roles.tenant_id'))
            ->orWhereIn('business_id', $this->chains($userId)->select('id'));
    }

    /** The approved chains this user owns. */
    private function chains(int $userId): Builder
    {
        return Business::query()
            ->where('owner_user_id', $userId)
            ->where('status', Business::STATUS_APPROVED);
    }
}
