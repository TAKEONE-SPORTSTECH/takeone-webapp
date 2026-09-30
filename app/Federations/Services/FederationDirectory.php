<?php

namespace App\Federations\Services;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationStaff;
use App\Members\Models\User;
use App\Support\Countries;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The federation tree and its staff, in the shapes the designed dashboard
 * reads (`fed-orgs`, `fed-users`, `fed-region`) — and the only door through
 * which the dashboard may change them.
 *
 * The page proposes a whole list (that is how the prototype saves); this class
 * treats it as a REQUEST and applies only what the acting user may do:
 *
 *  - a user sees their own federation and everything BELOW it, plus the names
 *    of the bodies above it (the page's breadcrumb). Never a sibling.
 *  - only a manager (no sport limit) may change structure, staff or region.
 *  - a body may not be moved, deepened past three levels, or reach above the
 *    user's own federation. The user's own federation's payer and status are
 *    not theirs to change.
 *
 * Whatever the page sent, the answer is the server's list; the page is told
 * to redraw from it.
 */
class FederationDirectory
{
    use StoresBase64Images;

    /** The sports the dashboard offers when creating a body. */
    public const SPORTS = [
        'Karate', 'Taekwondo', 'Judo', 'Jiu-Jitsu', 'Boxing', 'Kickboxing', 'Muay Thai',
        'Wrestling', 'Kung Fu / Wushu', 'MMA', 'Fencing',
    ];

    /** @var Collection<int, Federation>|null */
    private ?Collection $all = null;

    /**
     * What this user may run, or null for nothing.
     *
     * @return array{root: Federation, manager: bool, sports: array<int,string>, role: string}|null
     */
    public function access(User $user): ?array
    {
        // Someone who owns a body AND one beneath it lands on the higher one —
        // it already shows everything below. Same depth: the oldest, as before.
        $owned = Federation::approved()->whereNull('suspended_at')
            ->where('owner_user_id', $user->id)->oldest('id')->get()
            ->filter(fn (Federation $f) => $this->usable($f))
            ->sortBy(fn (Federation $f) => count($this->lineage($f)))
            ->first();
        if ($owned) {
            return ['root' => $owned, 'manager' => true, 'sports' => [], 'role' => 'Federation Manager'];
        }

        $row = FederationStaff::where('user_id', $user->id)->with('federation')->oldest('id')->get()
            ->first(fn (FederationStaff $s) => $s->federation && $this->usable($s->federation));
        if ($row) {
            $sports = array_values(array_filter((array) $row->sports));

            return [
                'root' => $row->federation,
                'manager' => $sports === [],
                'sports' => $sports,
                'role' => $row->role ?: 'Staff',
            ];
        }

        if ($user->isSuperAdmin()) {
            $top = Federation::approved()->whereNull('parent_id')->oldest('id')->first();

            return $top ? ['root' => $top, 'manager' => true, 'sports' => [], 'role' => 'Platform administrator'] : null;
        }

        return null;
    }

    /** Approved, not suspended, and nothing above it suspended. */
    private function usable(Federation $f): bool
    {
        if (! $f->isApproved()) {
            return false;
        }
        foreach ($this->lineage($f) as $body) {
            if ($body->isSuspended()) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ tree

    /** @return Collection<int, Federation> keyed by id */
    private function all(): Collection
    {
        return $this->all ??= Federation::approved()->get()->keyBy('id');
    }

    private function fresh(): void
    {
        $this->all = null;
    }

    /** The body and every body above it, nearest first. @return array<int, Federation> */
    private function lineage(Federation $f): array
    {
        $out = [];
        $cur = $f;
        $guard = 0;
        while ($cur && $guard++ < 10) {
            $out[] = $cur;
            $cur = $cur->parent_id ? $this->all()->get($cur->parent_id) : null;
        }

        return $out;
    }

    /** The body and everything below it. @return array<int, Federation> */
    public function subtree(Federation $root): array
    {
        $out = [$root];
        $walk = function (int $id, int $depth) use (&$walk, &$out) {
            if ($depth > Federation::MAX_DEPTH) {
                return;
            }
            foreach ($this->all()->where('parent_id', $id) as $child) {
                $out[] = $child;
                $walk($child->id, $depth + 1);
            }
        };
        $walk($root->id, 1);

        return $out;
    }

    /**
     * Athletes a body has in a sport. Real records only: until athletes are
     * registered under the body there are none — never a guess, because this
     * number picks the subscription tier.
     */
    public function athleteCount(Federation $f, string $sport): int
    {
        return 0;
    }

    public function depth(Federation $f): int
    {
        return count($this->lineage($f));
    }

    // -------------------------------------------------------------- payloads

    /** `fed-orgs`: the ancestors (breadcrumb only) and the subtree in full. */
    public function orgs(Federation $root): array
    {
        $rows = [];

        foreach (array_reverse(array_slice($this->lineage($root), 1)) as $above) {
            // Above the user's own body: enough to draw the path and find who
            // pays, nothing of what it runs.
            $rows[] = [
                'id' => $above->uuid,
                'name' => $above->name,
                'parentId' => $above->parent?->uuid,
                'sports' => [],
                'athletes' => (object) [],
                'payer' => $above->payer ?: Federation::PAYER_SELF,
                'status' => $above->isSuspended() ? 'Suspended' : 'Active',
                'logo' => null,
                'logoShape' => $above->logo_shape ?: 'circle',
                'readOnly' => true,
            ];
        }

        foreach ($this->subtree($root) as $f) {
            $sports = array_values((array) $f->sports);
            $rows[] = [
                'id' => $f->uuid,
                'name' => $f->name,
                'parentId' => $f->parent_id ? $this->all()->get($f->parent_id)?->uuid : null,
                'sports' => $sports,
                // Athlete counts come from real records only; until athletes are
                // registered under the body there are none (never a guess).
                'athletes' => (object) collect($sports)->mapWithKeys(fn ($sp) => [$sp => $this->athleteCount($f, $sp)])->all(),
                'payer' => $f->parent_id ? ($f->payer ?: Federation::PAYER_SELF) : Federation::PAYER_SELF,
                'status' => $f->isSuspended() ? 'Suspended' : 'Active',
                'logo' => $f->logo ? route('federations.logo', ['federation' => $f->uuid, 'v' => substr(md5($f->logo), 0, 8)]) : null,
                'logoShape' => $f->logo_shape ?: 'circle',
            ];
        }

        return $rows;
    }

    /** `fed-users`: everyone with access to the subtree, the viewer included. */
    public function users(Federation $root, User $viewer, array $access): array
    {
        $bodies = collect($this->subtree($root));
        $ids = $bodies->pluck('id');
        $out = [];

        $push = function (User $u, Federation $f, string $role, array $sports) use (&$out) {
            $email = strtolower((string) $u->email);
            if ($email === '' || isset($out[$email])) {
                return;
            }
            $out[$email] = [
                'email' => $email,
                'name' => $u->full_name ?: ($u->name ?: $email),
                'role' => $role,
                'orgId' => $f->uuid,
                'sports' => array_values($sports),
                // Only whether 2FA is on — the secret itself never leaves the server.
                'totp' => $u->hasTwoFactorEnabled() ? 'PLATFORM' : null,
                'backup' => [],
                'isOwner' => (int) $f->owner_user_id === (int) $u->id,
            ];
        };

        $push($viewer, $access['root'], $access['role'], $access['sports']);

        foreach ($bodies as $f) {
            if ($f->owner) {
                $push($f->owner, $f, 'Federation Manager', []);
            }
        }

        FederationStaff::whereIn('federation_id', $ids)->with('user', 'federation')->oldest('id')->get()
            ->each(function (FederationStaff $s) use ($push) {
                if ($s->user) {
                    $push($s->user, $s->federation, $s->role ?: 'Staff', (array) $s->sports);
                }
            });

        return array_values($out);
    }

    /** `fed-region`: the country, time zone and currency of the user's own body. */
    public function region(Federation $root): array
    {
        $country = strtoupper((string) Countries::iso2($root->country)) ?: 'BH';

        return [
            'country' => $country,
            'tz' => $root->timezone ?: 'Asia/Bahrain',
            'cur' => $root->currency ?: 'BHD',
        ];
    }

    // ----------------------------------------------------------------- writes

    /**
     * Apply the list the page saved. Returns the server's list.
     *
     * @param  array<int, mixed>  $posted
     */
    public function syncOrgs(User $user, array $access, array $posted): array
    {
        $root = $access['root'];
        if (! $access['manager']) {
            return $this->orgs($root);
        }

        $inScope = collect($this->subtree($root))->keyBy('uuid');

        DB::transaction(function () use ($posted, $inScope, $root, $user) {
            $created = [];

            foreach ($posted as $row) {
                if (! is_array($row) || ! is_string($row['id'] ?? null)) {
                    continue;
                }
                $id = $row['id'];
                $existing = $inScope->get($id);

                if ($existing) {
                    $this->updateBody($existing, $row, $existing->is($root));

                    continue;
                }

                // A new body: a fresh uuid the page drew, under a body in scope.
                $parent = $inScope->get($row['parentId'] ?? '') ?? ($created[$row['parentId'] ?? ''] ?? null);
                if (! $parent || ! Str::isUuid($id) || Federation::withTrashed()->where('uuid', $id)->exists()) {
                    continue;
                }
                if ($this->depthOf($parent, $created) >= Federation::MAX_DEPTH || $parent->isSuspended()) {
                    continue;
                }
                $name = $this->cleanName($row['name'] ?? '');
                $sports = $this->cleanSports($row['sports'] ?? []);
                if ($name === '' || $sports === []) {
                    continue;
                }

                $body = new Federation([
                    'owner_user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'payer' => ($row['payer'] ?? '') === Federation::PAYER_SELF ? Federation::PAYER_SELF : Federation::PAYER_PARENT,
                    'name' => $name,
                    'sports' => $sports,
                    'sport' => strtolower($sports[0]),
                    'country' => $root->country,
                    'timezone' => $root->timezone,
                    'currency' => $root->currency,
                    'status' => Federation::STATUS_APPROVED,
                    'approved_at' => now(),
                    'approved_by' => $user->id,
                ]);
                $body->uuid = $id;
                $body->save();
                $created[$id] = $body;
            }
        });

        $this->fresh();

        return $this->orgs($root->fresh());
    }

    private function depthOf(Federation $f, array $created): int
    {
        $this->fresh();
        foreach ($created as $c) {
            $this->all()->put($c->id, $c);
        }

        return $this->depth($f);
    }

    private function updateBody(Federation $body, array $row, bool $isRoot): void
    {
        $name = $this->cleanName($row['name'] ?? '');
        if ($name !== '') {
            $body->name = $name;
        }

        $shape = $row['logoShape'] ?? null;
        if (in_array($shape, ['circle', 'rect'], true)) {
            $body->logo_shape = $shape;
        }

        $logo = $row['logo'] ?? null;
        if (is_string($logo) && str_starts_with($logo, 'data:image/')) {
            $path = $this->storeBase64Image($logo, 'federations/'.$body->uuid.'/branding', (string) Str::uuid(), 'public');
            if ($path !== null) {
                $old = $body->logo;
                $body->logo = $path;
                if ($old && $old !== $path) {
                    Storage::disk('public')->delete($old);
                }
            }
        } elseif ($logo === null && array_key_exists('logo', $row) && $body->logo) {
            Storage::disk('public')->delete($body->logo);
            $body->logo = null;
        }

        // The user's own body: its payer and status belong to the level above.
        if (! $isRoot) {
            if (in_array($row['payer'] ?? null, [Federation::PAYER_SELF, Federation::PAYER_PARENT], true)) {
                $body->payer = $row['payer'];
            }
            if (($row['status'] ?? null) === 'Suspended' && ! $body->isSuspended()) {
                $body->suspended_at = now();
            } elseif (($row['status'] ?? null) === 'Active' && $body->isSuspended()) {
                $body->suspended_at = null;
            }
        }

        $body->save();
    }

    private function cleanName(mixed $name): string
    {
        return is_string($name) ? mb_substr(trim(strip_tags($name)), 0, 150) : '';
    }

    /** @return array<int, string> */
    private function cleanSports(mixed $sports): array
    {
        return array_values(array_intersect(self::SPORTS, is_array($sports) ? $sports : []));
    }

    /**
     * Apply the staff list the page saved: add who is new, remove who is gone.
     *
     * @param  array<int, mixed>  $posted
     * @return array{users: array, message: ?string}
     */
    public function syncUsers(User $user, array $access, array $posted): array
    {
        $root = $access['root'];
        if (! $access['manager']) {
            return ['users' => $this->users($root, $user, $access), 'message' => null];
        }

        $bodies = collect($this->subtree($root))->keyBy('uuid');
        $current = collect($this->users($root, $user, $access))->keyBy('email');
        $wanted = collect($posted)->filter(fn ($r) => is_array($r) && is_string($r['email'] ?? null))
            ->keyBy(fn ($r) => strtolower(trim($r['email'])));
        $invited = null;

        DB::transaction(function () use ($bodies, $current, $wanted, $user, &$invited) {
            // Removed: a staff row in scope that the page no longer lists.
            foreach ($current as $email => $row) {
                if ($wanted->has($email) || $row['isOwner'] || $email === strtolower((string) $user->email)) {
                    continue;
                }
                $member = User::where('email', $email)->first();
                if ($member) {
                    FederationStaff::whereIn('federation_id', $bodies->pluck('id'))->where('user_id', $member->id)->delete();
                }
            }

            // Added: a row the page lists that is not staff yet.
            foreach ($wanted as $email => $row) {
                if ($current->has($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $body = $bodies->get($row['orgId'] ?? '');
                if (! $body || $body->isSuspended()) {
                    continue;
                }
                $name = $this->cleanName($row['name'] ?? '');
                $bodySports = collect($this->subtree($body))->flatMap(fn ($f) => (array) $f->sports)->unique()->all();
                $sports = array_values(array_intersect($bodySports, is_array($row['sports'] ?? null) ? $row['sports'] : []));

                $member = User::where('email', $email)->first();
                $isNew = false;
                if (! $member) {
                    if ($name === '') {
                        continue;
                    }
                    $member = User::create([
                        'full_name' => $name,
                        'name' => $name,
                        'email' => $email,
                        // Unusable until they set their own from the invitation.
                        'password' => Hash::make(Str::random(48)),
                        'is_discoverable' => false,
                    ]);
                    $isNew = true;
                }

                FederationStaff::updateOrCreate(
                    ['federation_id' => $body->id, 'user_id' => $member->id],
                    ['role' => mb_substr(trim((string) ($row['role'] ?? '')), 0, 80) ?: 'Staff', 'sports' => $sports ?: null, 'invited_by' => $user->id],
                );

                $invited = $email;
                if ($isNew) {
                    // The invitation: a one-time link to choose a password.
                    // Its link opens the federation's own reset page, not the platform's.
                    rescue(fn () => Password::sendResetLink(['email' => $email], function ($u, $token) {
                        $u->notify(new \App\Federations\Notifications\FederationResetPassword($token));
                    }), report: true);
                }
            }
        });

        return ['users' => $this->users($root, $user, $access), 'message' => $invited ? 'Invitation sent to '.$invited.'.' : null];
    }

    /** @return array{country: string, tz: string, cur: string} */
    public function syncRegion(array $access, array $posted): array
    {
        $root = $access['root'];
        if (! $access['manager']) {
            return $this->region($root);
        }

        $country = Countries::iso2((string) ($posted['country'] ?? ''));
        $tz = (string) ($posted['tz'] ?? '');
        $cur = strtoupper((string) ($posted['cur'] ?? ''));

        if ($country) {
            $root->country = strtoupper($country);
        }
        if (in_array($tz, timezone_identifiers_list(), true)) {
            $root->timezone = $tz;
        }
        if (preg_match('/^[A-Z]{3}$/', $cur)) {
            $root->currency = $cur;
        }
        $root->save();

        return $this->region($root);
    }
}
