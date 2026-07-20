<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\ClubMemberSubscription;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClubRoleController extends Controller
{
    use HandlesClubAuthorization;

    /** Seeded roles that cannot be renamed or deleted. */
    private const SYSTEM_ROLES = ['super-admin', 'club-admin', 'instructor', 'staff', 'moderator'];

    /** Allowed badge colours. */
    private const COLORS = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];

    /**
     * Permissions grouped into human-readable categories. Anything not listed falls under "Other".
     */
    private const PERMISSION_GROUPS = [
        'Members' => ['view-members', 'manage-members', 'manage-all-members'],
        'Club' => ['view-club-info', 'manage-club-details', 'manage-all-clubs'],
        'Programs' => ['manage-activities', 'manage-packages', 'manage-facilities', 'manage-instructors'],
        'Finance' => ['manage-financials', 'view-analytics', 'view-platform-analytics'],
        'Content' => ['manage-gallery'],
        'Messaging' => ['send-messages', 'manage-messages'],
        'Attendance' => ['mark-attendance'],
        'Profile' => ['view-own-profile', 'update-own-profile'],
        'System' => ['database-backup'],
    ];

    public function roles(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $members = ClubMemberSubscription::where('tenant_id', $clubId)
            ->with('user')
            ->get()
            ->unique('user_id');

        // Packages for the walk-in "Add Member" modal reused on this page.
        $packages = \App\Models\ClubPackage::where('tenant_id', $clubId)->with('activities.equipment')->get();
        app(\App\Services\RegistrationCostService::class)->attachEquipmentToPackages($packages, $clubId);

        // Roles shown in the assign dropdown / member badges — every assignable role
        // (excludes super-admin platform role and the implicit "member" default), so
        // custom roles created on this page appear automatically.
        $availableRoles = Role::whereNotIn('slug', ['super-admin', 'member'])
            ->where('slug', 'not like', 'member-%')
            ->orderBy('id')->get();

        // Card + modal data.
        $allPermissions = Permission::orderBy('name')->get(['id', 'slug', 'name', 'description']);
        $grouped = $this->groupPermissions($allPermissions);

        $groupsData = [];
        foreach ($grouped as $label => $perms) {
            $groupsData[] = [
                'label' => $label,
                'perms' => collect($perms)->map(fn ($p) => [
                    'slug' => $p->slug, 'name' => $p->name, 'desc' => $p->description,
                ])->all(),
            ];
        }

        $rolesData = Role::with('permissions:id,slug')
            ->where('slug', 'not like', 'member-%')
            ->orderBy('id')->get()
            ->map(fn (Role $r) => $this->roleCard($r, $clubId))
            ->values()->all();

        $canEdit = (bool) (auth()->user()?->isSuperAdmin());
        $totalPerms = $allPermissions->count();

        // The actual "team": users who hold a real assigned role in THIS club (not
        // the implicit "member" default, not the platform super-admin). These are
        // who the roles page is really about — distinct from plain club members.
        $holderIds = \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $clubId)
            ->where('roles.slug', '!=', 'member')
            ->where('roles.slug', '!=', 'super-admin')
            ->pluck('user_roles.user_id')->unique()->all();

        $roleHolders = User::whereIn('id', $holderIds)->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->full_name,
            'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
            'roles' => $u->getRolesForTenant($clubId)
                ->reject(fn ($r) => $r->slug === 'member' || $r->slug === 'super-admin')
                ->pluck('name')->values()->all(),
        ])->values();

        return view(\App\Support\ClubView::pick('roles'), compact(
            'club', 'members', 'availableRoles', 'rolesData', 'groupsData', 'canEdit', 'totalPerms', 'packages', 'roleHolders'
        ));
    }

    /** Create a custom role (super-admin only). */
    public function createRole(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        if (! auth()->user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only super-admins can manage roles.'], 403);
        }

        $data = $this->validateRole($request);

        $slug = $this->uniqueSlug($data['label']);
        $attrs = ['name' => $data['label'], 'slug' => $slug, 'description' => $data['description'] ?? null];
        if (Schema::hasColumn('roles', 'color')) {
            $attrs['color'] = $data['color'];
        }
        $role = Role::create($attrs);
        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

        return response()->json([
            'success' => true,
            'message' => "Role “{$role->name}” created.",
            'role' => $this->roleCard($role->load('permissions:id,slug'), $club->id),
        ]);
    }

    /** Update a role's colour, name (custom only) and permissions (super-admin only). */
    public function updateRole(Request $request, Tenant $club, Role $role)
    {
        $this->authorizeClub($club);
        if (! auth()->user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only super-admins can manage roles.'], 403);
        }

        $data = $this->validateRole($request);
        $isSystem = in_array($role->slug, self::SYSTEM_ROLES, true);

        if (! $isSystem) {
            $role->name = $data['label'];
            $role->description = $data['description'] ?? $role->description;
        }
        if (Schema::hasColumn('roles', 'color')) {
            $role->color = $data['color'];
        }
        $role->save();
        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

        return response()->json([
            'success' => true,
            'message' => "Role “{$role->name}” updated.",
            'role' => $this->roleCard($role->load('permissions:id,slug'), $club->id),
        ]);
    }

    /** Delete a custom role (super-admin only; system roles are protected). */
    public function deleteRole(Tenant $club, Role $role)
    {
        $this->authorizeClub($club);
        if (! auth()->user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only super-admins can manage roles.'], 403);
        }
        if (in_array($role->slug, self::SYSTEM_ROLES, true)) {
            return response()->json(['success' => false, 'message' => 'System roles cannot be deleted.'], 422);
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $id = $role->id;
        $name = $role->name;
        $role->delete();

        return response()->json(['success' => true, 'message' => "Role “{$name}” deleted.", 'id' => $id]);
    }

    public function storeRole(RoleRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        // Hard allowlist: never let a club admin assign super-admin, the implicit
        // "member" default, or a per-member pseudo-role. Without this a club-level
        // actor could POST role=super-admin and escalate to a platform role.
        if (! in_array($request->role, $this->assignableRoleSlugs(), true)) {
            return back()->with('error', 'That role cannot be assigned.');
        }

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role, $club->id);

        return back()->with('success', 'Role assigned successfully.');
    }

    public function destroyRole(RoleRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role, $club->id);

        return back()->with('success', 'Role removed successfully.');
    }

    /** Per-member custom role slug for a club. */
    private function memberRoleSlug(int $clubId, int $userId): string
    {
        return "member-{$clubId}-{$userId}";
    }

    /**
     * Slugs that may be assigned to a club member. Excludes the platform super-admin,
     * the implicit "member" default, and per-member custom-permission pseudo-roles
     * (member-{club}-{user}). Used as a hard allowlist on every assignment path so a
     * crafted request cannot grant a higher-privilege or internal role.
     */
    private function assignableRoleSlugs(): array
    {
        return Role::whereNotIn('slug', ['super-admin', 'member'])
            ->where('slug', 'not like', 'member-%')
            ->pluck('slug')->all();
    }

    /** Return a member's current effective permissions for this club (for the access modal). */
    public function memberPermissions(Tenant $club, User $user)
    {
        $this->authorizeClub($club);

        $roles = $user->getRolesForTenant($club->id);
        $customSlug = $this->memberRoleSlug($club->id, $user->id);
        $custom = $roles->firstWhere('slug', $customSlug);

        $permissions = $roles->flatMap(fn (Role $r) => $r->permissions()->pluck('slug')->all())
            ->unique()->values()->all();

        // First standard (non-custom) role, used to pre-select the dropdown.
        $standard = $roles->first(fn (Role $r) => $r->slug !== $customSlug
            && ! in_array($r->slug, ['super-admin', 'member'], true));

        // Full grouped permission catalog so the editor can render the checklist.
        $allPermissions = Permission::orderBy('name')->get(['id', 'slug', 'name', 'description']);
        $groupsData = [];
        foreach ($this->groupPermissions($allPermissions) as $label => $perms) {
            $groupsData[] = [
                'label' => $label,
                'perms' => collect($perms)->map(fn ($p) => [
                    'slug' => $p->slug, 'name' => $p->name, 'desc' => $p->description,
                ])->all(),
            ];
        }

        return response()->json([
            'success' => true,
            'permissions' => $permissions,
            'custom' => (bool) $custom,
            'role' => $standard?->slug ?? '',
            'groups' => $groupsData,
            'total' => $allPermissions->count(),
            'canEdit' => (bool) auth()->user()?->isSuperAdmin(),
        ]);
    }

    /**
     * Assign a member's access: either a standard role (club-admin allowed) or a custom
     * per-member permission set (super-admin only — mirrors the role-editor restriction so
     * club-admins cannot grant themselves platform-level permissions).
     */
    public function storeMemberPermissions(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'exists:roles,slug'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $clubId = $club->id;
        $isCustom = $request->has('permissions') && is_array($request->input('permissions'));

        // Custom permission editing is a super-admin-only capability.
        if ($isCustom && ! auth()->user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only super-admins can set custom permissions.'], 403);
        }

        if ($isCustom) {
            $slug = $this->memberRoleSlug($clubId, $user->id);
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => 'Custom Access', 'description' => "Custom permissions for {$user->full_name}"]
            );
            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

            // The custom set becomes the member's effective access for this club.
            $user->roles()->wherePivot('tenant_id', $clubId)->detach();
            if (! empty($data['permissions'])) {
                $user->roles()->attach($role->id, ['tenant_id' => $clubId]);
            }
            \DB::table('sessions')->where('user_id', $user->id)->delete();

            return response()->json(['success' => true, 'message' => 'Custom permissions saved.']);
        }

        // Standard role assignment.
        if (empty($data['role'])) {
            return response()->json(['success' => false, 'message' => 'Please choose a role.'], 422);
        }
        if (! in_array($data['role'], $this->assignableRoleSlugs(), true)) {
            return response()->json(['success' => false, 'message' => 'That role cannot be assigned.'], 422);
        }
        if (! $user->hasRole($data['role'], $clubId)) {
            $user->assignRole($data['role'], $clubId);
        }

        return response()->json(['success' => true, 'message' => 'Role assigned successfully.']);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function validateRole(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:'.implode(',', self::COLORS)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);
    }

    private function roleCard(Role $role, int $clubId): array
    {
        return [
            'id' => $role->id,
            'label' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'color' => $role->color ?: $this->defaultColor($role->slug),
            'isSystem' => in_array($role->slug, self::SYSTEM_ROLES, true),
            'userCount' => $role->users()->wherePivot('tenant_id', $clubId)->count(),
            'permissions' => $role->permissions->pluck('slug')->values()->all(),
        ];
    }

    private function defaultColor(string $slug): string
    {
        return match ($slug) {
            'club-admin', 'super-admin' => 'danger',
            'instructor' => 'info',
            'moderator' => 'warning',
            default => 'secondary',
        };
    }

    private function permissionIds(array $slugs): array
    {
        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function groupPermissions($permissions): array
    {
        $bySlug = $permissions->keyBy('slug');
        $grouped = [];

        foreach (self::PERMISSION_GROUPS as $label => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                if ($p = $bySlug->pull($slug)) {
                    $items[] = $p;
                }
            }
            if ($items) {
                $grouped[$label] = $items;
            }
        }

        if ($bySlug->isNotEmpty()) {
            $grouped['Other'] = $bySlug->values()->all();
        }

        return $grouped;
    }
}
