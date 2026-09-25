<?php

namespace App\Federations\Services;

use App\Clubs\Models\Tenant;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationStaff;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Members\Support\MemberLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Takeone\Realtime\Support\Topics;

/**
 * Federation staff adding a club that already exists on the platform.
 *
 * Search finds a club by its name, or by its owner's name, phone or email —
 * owners go through MemberLookup, the platform's one "find a member", so a
 * phone typed as `+973 3774 3277` or `37743277` finds the same owner. A result
 * shows only what is needed to recognise the club: name, logo, country, the
 * owner's name, and the owner's contact MASKED — the search is a way to pick
 * a club, not a way to read a stranger's email.
 *
 * Adding affiliates the club with the viewer's own federation (approved at
 * once: the federation is the one deciding) and tells the owner.
 */
class FederationClubPicker
{
    private const LIMIT = 20;

    public function __construct(
        private FederationDirectory $directory,
        private FederationDesk $desk,
    ) {}

    /** Only a manager (not a sport-limited staff member) changes the membership. */
    public function mayAdd(array $access): bool
    {
        return (bool) ($access['manager'] ?? false);
    }

    /** @return array<int, array<string, mixed>> */
    public function search(array $access, string $typed): array
    {
        $q = trim(str_replace(['%', '_'], '', $typed));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            return [];
        }

        $owners = MemberLookup::search($q, self::LIMIT)->pluck('id');
        $like = '%'.$q.'%';
        $clubs = Tenant::query()
            ->with('owner:id,full_name,name,email,mobile')
            ->where(fn ($w) => $w->where('club_name', 'like', $like)
                ->orWhere('owner_name', 'like', $like)
                ->orWhere('owner_email', 'like', mb_strtolower($like))
                ->orWhereIn('owner_user_id', $owners))
            ->orderBy('club_name')->limit(self::LIMIT)
            ->get(['id', 'owner_user_id', 'club_name', 'slug', 'logo', 'country', 'owner_name', 'owner_email']);

        $linked = FederationClub::whereIn('federation_id', $this->ids($access))
            ->where('status', FederationClub::STATUS_APPROVED)
            ->whereIn('tenant_id', $clubs->pluck('id'))->pluck('tenant_id')->flip();

        return $clubs->map(fn (Tenant $t) => [
            'key' => $t->slug,
            'name' => $t->club_name,
            'logo' => $t->logo ? file_url($t->logo) : null,
            'country' => $t->country ? strtoupper((string) $t->country) : null,
            'owner' => $t->owner_name ?: ($t->owner?->full_name ?: $t->owner?->name),
            'ownerEmail' => $this->maskEmail($t->owner_email ?: $t->owner?->email),
            'ownerPhone' => $this->maskPhone($t->owner?->mobile),
            'linked' => isset($linked[$t->id]),
        ])->values()->all();
    }

    /** Affiliate the club; idempotent. False when the club is not found or the viewer may not. */
    public function add(User $by, array $access, string $slug): bool
    {
        if (! $this->mayAdd($access)) {
            return false;
        }
        $club = Tenant::where('slug', $slug)->first();
        if (! $club) {
            return false;
        }
        $root = $access['root'];

        $already = FederationClub::whereIn('federation_id', $this->ids($access))
            ->where('tenant_id', $club->id)->where('status', FederationClub::STATUS_APPROVED)->exists();
        if ($already) {
            return true;
        }

        DB::transaction(fn () => FederationClub::updateOrCreate(
            ['federation_id' => $root->id, 'tenant_id' => $club->id],
            ['status' => FederationClub::STATUS_APPROVED, 'decided_at' => now(), 'decided_by' => $by->id],
        ));

        activity()->causedBy($by)->performedOn($club)
            ->withProperties(['federation' => $root->uuid ?? $root->id])->log('federation.club_added');

        if ($club->owner_user_id) {
            rescue(fn () => UserNotification::notifyUser((int) $club->owner_user_id, 'federation_club_added',
                $root->name.' added '.$club->club_name.' to its clubs', [
                    'actor_id' => $by->id,
                    'tenant_id' => $club->id,
                    'body' => 'Your club is now affiliated with '.$root->name.'.',
                    'icon' => 'bi-building-check',
                ]), report: true);
        }
        $this->signalStaff($access, $by->id);

        return true;
    }

    /** The dashboard's club list and personal links, as the store holds them. */
    public function clubs(array $access): array
    {
        $store = $this->desk->store($access);

        return ['clubs' => $store['clubs'], 'clubLinks' => $store['clubLinks'], 'directory' => $store['directory']];
    }

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }

    /** Refresh signal only — each dashboard re-fetches what its viewer may see. */
    private function signalStaff(array $access, int $except): void
    {
        $f = $access['root'];
        $ids = [];
        for ($guard = 0; $f && $guard < 10; $guard++) {
            $ids[] = (int) $f->owner_user_id;
            array_push($ids, ...FederationStaff::where('federation_id', $f->id)->pluck('user_id')->map(fn ($i) => (int) $i)->all());
            $f = $f->parent_id ? $f->newQuery()->find($f->parent_id) : null;
        }
        $batch = [];
        foreach (array_unique($ids) as $uid) {
            if ($uid && $uid !== $except) {
                $batch[] = ['topic' => Topics::user($uid, 'federation'), 'payload' => ['action' => 'clubs']];
            }
        }
        if ($batch !== []) {
            rescue(fn () => \Realtime()->publishMany($batch), report: true);
        }
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$user, $domain] = explode('@', $email, 2);

        return mb_substr($user, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($user) - 1))).'@'.$domain;
    }

    private function maskPhone(mixed $mobile): ?string
    {
        $m = is_array($mobile) ? $mobile : (json_decode((string) $mobile, true) ?: ['number' => (string) $mobile]);
        $digits = preg_replace('/\D/', '', (string) ($m['number'] ?? ''));
        if (strlen($digits) < 4) {
            return null;
        }

        return (! empty($m['code']) ? $m['code'].' ' : '').'•••• '.substr($digits, -3);
    }
}
