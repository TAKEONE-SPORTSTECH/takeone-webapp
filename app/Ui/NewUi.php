<?php

namespace App\Ui;

use App\Clubs\Models\Tenant;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The one place that decides whether this request gets the new UI.
 *
 * `off` (the default) always answers false, so with the flag off nothing in
 * app/Ui is ever reached from an existing page. `preview` answers true for a
 * super-admin unless they switched it off for their own session. `on`
 * answers true for everyone.
 */
class NewUi
{
    public const SESSION_KEY = 'new_ui_preview';

    public static function mode(): string
    {
        $mode = config('features.new_ui', 'off');

        return in_array($mode, ['off', 'preview', 'on'], true) ? $mode : 'off';
    }

    public static function active(): bool
    {
        return match (self::mode()) {
            'on' => true,
            // A super-admin sees it by default and may step back to classic.
            'preview' => self::canPreview() && (bool) session(self::SESSION_KEY, true),
            default => false,
        };
    }

    /** Who may switch the preview on for themselves. Grants nothing else. */
    public static function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->isSuperAdmin();
    }

    /**
     * Where "Manage" takes this person, or null when they manage nothing (then
     * the switch shows Play alone). Until the Manage shell lands (Phase 5) it
     * opens the screen they already use: the business dashboard, the club
     * door, the federation app, or the platform admin.
     *
     * @return array{method: 'get'|'post', url: string, fields?: array<string, string>}|null
     */
    public static function manageTarget(?User $user = null): ?array
    {
        $user ??= Auth::user();
        if (! $user) {
            return null;
        }

        // The Manage shell (Phase 5) lists everything the person manages.
        if (\App\Ui\Manage\ManageScope::orgsFor($user) !== [] || $user->isSuperAdmin()) {
            return ['method' => 'get', 'url' => route('me.manage')];
        }

        if ($user->hasApprovedBusiness()) {
            return ['method' => 'post', 'url' => route('view.switch'), 'fields' => ['mode' => 'business']];
        }

        if (Tenant::where('owner_user_id', $user->id)->exists()
            || $user->roles()->where('slug', 'club-admin')->exists()) {
            return ['method' => 'get', 'url' => route('club.entry')];
        }

        $federationId = Federation::where('owner_user_id', $user->id)->value('id')
            ?? DB::table('federation_staff')->where('user_id', $user->id)->value('federation_id');
        if ($federationId && ($uuid = Federation::whereKey($federationId)->value('uuid'))) {
            return ['method' => 'get', 'url' => route('federations.dashboard', $uuid)];
        }

        if ($user->isSuperAdmin()) {
            return ['method' => 'get', 'url' => route('admin.platform.index')];
        }

        return null;
    }
}
