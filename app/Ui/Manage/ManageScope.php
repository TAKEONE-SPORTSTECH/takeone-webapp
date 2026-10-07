<?php

namespace App\Ui\Manage;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything a person manages, as the Manage shell's organisation picker lists
 * it: their business (and the clubs inside it), the clubs they own or
 * administer, and the federations they run or staff.
 *
 * This is a LIST of doors, not a grant: every screen behind a door
 * re-authorises exactly as it always has. The rules mirror the club door
 * (owner · club-admin role · owner of the club's approved chain) and
 * User::holdsPrivilegedAccess() for federations.
 */
class ManageScope
{
    /** @return list<array<string, mixed>> */
    public static function orgsFor(User $user): array
    {
        $orgs = [];

        $business = Business::approved()->where('owner_user_id', $user->id)->first();
        $chainClubIds = [];
        if ($business) {
            $chainClubs = Tenant::where('business_id', $business->id)->orderBy('club_name')->get(['id', 'club_name', 'slug', 'logo', 'currency', 'address']);
            $chainClubIds = $chainClubs->pluck('id')->all();
            $orgs[] = self::row('business', 'b-'.$business->slug, $business->name, $business->logo, '#0f766e', 'owner', null, ['business' => $business, 'clubs' => $chainClubs]);
            foreach ($chainClubs as $c) {
                $orgs[] = self::club($c, 'owner', 'b-'.$business->slug);
            }
        }

        $owned = Tenant::where('owner_user_id', $user->id)->whereNotIn('id', $chainClubIds)->orderBy('club_name')->get(['id', 'club_name', 'slug', 'logo', 'currency', 'address']);
        foreach ($owned as $c) {
            $orgs[] = self::club($c, 'owner');
        }

        $adminIds = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)->where('roles.slug', 'club-admin')->whereNotNull('user_roles.tenant_id')
            ->pluck('user_roles.tenant_id')->all();
        $staffClubs = Tenant::whereIn('id', $adminIds)->whereNotIn('id', array_merge($chainClubIds, $owned->pluck('id')->all()))
            ->orderBy('club_name')->get(['id', 'club_name', 'slug', 'logo', 'currency', 'address']);
        foreach ($staffClubs as $c) {
            $orgs[] = self::club($c, 'admin');
        }

        $fedIds = Federation::where('owner_user_id', $user->id)->pluck('id')
            ->merge(DB::table('federation_staff')->where('user_id', $user->id)->pluck('federation_id'))->unique()->all();
        foreach (Federation::whereIn('id', $fedIds)->orderBy('name')->get(['id', 'uuid', 'name', 'logo', 'color', 'owner_user_id']) as $f) {
            $orgs[] = self::row('federation', 'f-'.$f->uuid, $f->name, $f->logo, $f->color ?: '#d97706', (int) $f->owner_user_id === (int) $user->id ? 'owner' : 'staff', null, ['federation' => $f]);
        }

        return $orgs;
    }

    private static function club(Tenant $c, string $role, ?string $parent = null): array
    {
        return self::row('club', 'c-'.$c->slug, (string) $c->club_name, $c->logo, '#e11d48', $role, $parent, ['club' => $c]);
    }

    private static function row(string $kind, string $key, string $name, ?string $logo, string $color, string $role, ?string $parent, array $model): array
    {
        return [
            'kind' => $kind, 'key' => $key, 'name' => $name, 'color' => $color, 'role' => $role, 'parent' => $parent,
            'logo' => $logo ? file_url($logo) : null,
            'initials' => mb_strtoupper(collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')),
        ] + $model;
    }
}
