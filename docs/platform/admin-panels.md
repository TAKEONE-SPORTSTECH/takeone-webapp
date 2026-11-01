# Admin panels — super-admin and club admin

Who may administer what, how a super-admin is made, and the screens and routes
behind `/admin` and `/admin/club/{club}`. Checked against the code on 31 Oct 2026.
Account recovery (who may set whose password) is in `account-recovery.md`.

## Roles and permissions

- **Tables:** `roles`, `permissions`, `role_permission`, `user_roles`.
  `user_roles.tenant_id` scopes a role to one club; it is **NULL for platform
  roles** such as `super-admin`.
- **Seeded by** `database/seeders/RolePermissionSeeder.php` (called from
  `DatabaseSeeder`): roles `super-admin`, `club-admin`, `instructor`, `member`,
  plus the platform and club permission slugs (`manage-all-clubs`,
  `manage-financials`, `enter-athletes`, …). If `admin:make-super` says the role
  is missing, this seeder has not run.
- **Checks** live on `App\Members\Models\User`: `hasRole()`, `hasPermission()`,
  `isSuperAdmin()`, `isClubAdmin()`, `assignRole()`, `removeRole()`.
  `assignRole()` / `removeRole()` delete the user's rows in `sessions`, so a role
  change takes effect on the next request.
- **Middleware aliases** (`bootstrap/app.php`): `role` →
  `App\Members\Middleware\CheckRole`, `permission` → `CheckPermission`,
  `tenant` → `App\Clubs\Middleware\SetCurrentTenant`, `two-factor` →
  `RequiresTwoFactor`. A forbidden browser request redirects to `/`; a JSON one
  gets 403 (CLAUDE.md, *403s Redirect on Web*).
- Club admins assign per-member access from the club's Roles page
  (`ClubRoleController`, `/admin/club/{club}/roles`).

## Making a super-admin

- **Public registration never grants super-admin.** It used to give the role to
  the first person who registered while none existed. That was removed on
  3 Oct 2026 (`app/Members/Auth/RegisteredUserController.php`): losing the only
  super-admin would have handed the platform to the next stranger to sign up.
- **From the server:** `php artisan admin:make-super {email}`
  (`app/Members/Commands/MakeSuperAdmin.php`). The user must already exist. Run
  artisan as `www-data`, never root.
- **Seeder:** `database/seeders/SuperAdminSeeder.php` (`firstOrCreate`, so it never
  overwrites an existing account) creates `superadmin@takeone.bh`, the platform's
  super-admin account. The demo dataset (`demo:seed`) is wired to that account too.
- **Its password** is reset like any other privileged account: **Forgot password**
  by email, or another super-admin uses **Regenerate password**
  (`member.regenerate-password`), which emails a new one. A club admin can never
  reset it (`account-recovery.md`).

## The super-admin panel — `/admin`

All routes: middleware `auth, verified, two-factor, role:super-admin`, prefix
`admin`, names `admin.*`. Main file: `app/Platform/routes.php`; controller
`App\Platform\Controllers\PlatformController`; views `platform::` in
`app/Platform/resources/views/` (desktop, with `mobile/` twins). Layouts:
`layouts/admin.blade.php` and `layouts/admin-mobile.blade.php`; desktop sidebar
links navigate in place (CLAUDE.md, *Admin Sidebar SPA Navigation*).

| Area | Routes |
|---|---|
| Dashboard | `GET /admin` (`admin.platform.index`) |
| Clubs | `/admin/clubs` list, `create`, `{club}/edit`, `DELETE {club}`, `{club}/removal` (guided removal, `ClubRemovalController`), logo/cover upload |
| Club create/update + lookups | `POST /admin/clubs`, `PUT /admin/clubs/{club}`, `/admin/api/users[...]`, `/admin/api/clubs/{id}`, `/admin/api/clubs/check-slug` — in `app/Clubs/routes.php`, served by `ClubApiController` for `<x-club-modal>` (see `club-modal.md`) |
| Members | `/admin/members` list, duplicates, merge/undo, and per-member `/admin/members/{id}` show/edit/update/destroy, picture, health, tournament, club membership, verify email |
| Activity catalogue | `/admin/activities` |
| Settings | `/admin/settings` (platform settings, corner side, WhatsApp, reset-baseline) |
| Logs / audit | `/admin/logs`, `/admin/audit-log` |
| Featured events | `/admin/featured-events` |
| JSON backup | `/admin/backup` (see below) |

Other modules add their own `/admin/*` screens under the same stack: `app/Ai`,
`app/Forms`, `app/Duplicates`, and the remainder in `routes/web.php`.

**Super-admin member pages are separate from `/family/*`.** `/family/{id}` only
serves a real guardian→dependent relationship; a super-admin opens anyone through
`/admin/members/{id}`. The member views receive a stand-in relationship with
`relationship_type = 'admin_view'`, which makes their forms post to the
`admin.platform.members.*` routes. A super-admin cannot delete their own account
from there.

## The JSON backup page — `/admin/backup`

`downloadBackup()` dumps a **fixed list of 28 tables** (written in early 2026)
as plaintext JSON; `exportAuthUsers()` dumps id, name, email and **password
hash** of every user; `restoreBackup()` truncates and re-inserts every table named
in the uploaded file, inside a transaction.

This is **not** the platform's backup. The real one is `php artisan takeone:backup`
(nightly; SQLite snapshot or `pg_dump` for PostgreSQL), described in CLAUDE.md
RULE #2. Limits of the JSON page, as of 31 Oct 2026:

- The table list misses every table added since (events, shop, media, …).
- Restore runs `SET FOREIGN_KEY_CHECKS`, which is MySQL syntax. On PostgreSQL
  (the database since 30 Oct 2026) the statement fails, so restore fails.
- Restore trusts the table names in the file.
- `club_bank_accounts.account_number` and `iban` are encrypted with `APP_KEY`
  (`ClubBankAccount` accessors). A dump holds ciphertext, readable only on a
  server with the same key.

## The club admin panel — `/admin/club/{club}`

Every module's `routes-club-admin.php` is mounted by
`App\Support\Modules\ModuleServiceProvider` with middleware
`web, auth, verified, two-factor, tenant, throttle:admin-write`, prefix
`admin/club/{club}`, names `admin.club.*`. `{club}` resolves id-or-slug. The work
is split across controllers in `app/Clubs/Controllers/` (details, gallery,
facilities, instructors, activities, packages, members, financials, roles,
messages, analytics, …); views are `clubs::<section>/{index,mobile}`. Layouts:
`app/Clubs/resources/views/layouts/admin-club.blade.php` and
`admin-club-mobile.blade.php`.

## Rules and limitations

- Every sensitive action re-checks authorisation in the controller. The role
  middleware is the outer gate, not the only one.
- `/admin/members/{id}` binds the numeric user id. It is super-admin only, but it
  does not follow the *Unpredictable Resource Identifiers* rule.
- Two-factor is optional for everyone, super-admins included.
  `RequiresTwoFactor` only enforces the challenge for users who turned it on.
