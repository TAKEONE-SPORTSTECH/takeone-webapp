# TAKEONE Project — Claude Instructions

## TOP PRIORITY — Design-First: Creative, Innovative, Artistic, Modern — STRICT

**Rule:** Always focus on design. Every piece of UI built in this project must be creative, innovative, artistic, and modern. Never ship plain, default-looking, or "good enough" UI.

- **Design is a requirement, not a polish step.** Any new page, component, modal, sheet, card, list, empty state, or loading state must look intentionally designed — considered composition, rhythm, hierarchy, motion, and detail.
- **No generic AI/template aesthetics.** No stock boxy card grids, no unstyled form stacks, no bare tables. Bring art direction: layered depth, purposeful whitespace, expressive typography scale, meaningful iconography, tasteful micro-interactions and transitions.
- **Use the `frontend-design` skill for any new UI work** (see the Skills section) and follow the Design System tokens exactly.
- **Modern means:** fluid responsive layouts, smooth Alpine/CSS transitions, thoughtful states (hover / focus / active / empty / loading / error), and a mobile experience that is animated and creative (see [[feedback_mobile_creative_design]]), never a stripped-down desktop.
- **Boundaries this rule does NOT override:**
  - Design Rule #1 — never redesign existing UI unless explicitly asked. Creativity applies to what you are *building*, not to unrequested makeovers.
  - The Design System palette/typography/tokens — be creative *within* the system, never with off-palette one-off styles.
  - Security, Component-First reuse, and the No-Reload / Realtime rules. If a visual flourish weakens security or performance, it loses.

> Ask on every UI task: *would a great product designer be proud to ship this screen?* If not, it is not done.

---

## Project Overview

Laravel 12 SaaS platform for sports clubs (TAKEONE-SPORTSTECH). Multi-tenant architecture.

- **Stack:** PHP 8.2+, Laravel 12, Tailwind CSS 4, Vite 7, jQuery 3.7, Chart.js 4, Select2 4, Alpine.js 3
- **Custom package:** `takeone/cropper` (GitHub: TAKEONE-SPORTSTECH/laravel-image-cropper)
- **Auth:** Laravel Sanctum + email verification + optional 2FA

---

## Architecture

- **Multi-tenant:** Each sports club is a "tenant" with its own admin panel
- **Roles:** super-admin (platform), club owners/admins, regular members
- **Key directories:**
  - `app/Http/Controllers/Admin/` — ClubAdminController, PlatformController, ClubApiController, ClubMemberAdminController, etc.
  - `app/Http/Controllers/` — MemberController, FamilyController, InvoiceController, TrainerController, InstructorReviewController
  - `app/Models/` — Tenant, User, ClubInstructor, ClubPackage, ClubGalleryImage, ClubFacility, ClubActivity, ClubTransaction, ClubMemberSubscription, ClubReview, ClubSocialLink, ClubMessage, ClubAffiliation, Role, Permission, Membership, Invoice, Attendance, Goal, HealthRecord, TournamentEvent, PerformanceResult, SkillAcquisition, AffiliationMedia, NotesMedia, UserRelationship
  - `app/Services/` — FamilyService
  - `resources/views/` — admin/club/*, admin/platform/*, platform/, auth/, family/, trainer/

- **Trainer/Instructor data model:** `bio`, `skills`, `experience_years`, `is_personal_trainer` live on `User`. `ClubInstructor` holds only club-specific data: `tenant_id`, `user_id`, `role`, `rating`. Routes `/trainer/{user}` and `/t/{user}` use User model binding (User ID, not ClubInstructor ID).

---

## Route Structure

| Prefix | Description |
|--------|-------------|
| `/` | Redirect (explore if authed, login if not) |
| `/login`, `/register`, `/forgot-password`, etc. | Auth routes |
| `/mobile/{country}/{slug}` | Public club page (QR code) |
| `/explore`, `/{country}/clubs/{slug}`, `/trainer/{user}` | Authenticated platform browsing |
| `/admin/*` | Super-admin platform management |
| `/admin/club/{club}/*` | Club admin panel |
| `/family/*`, `/member/*` | Member management |
| `/bills/*` | Invoice/billing |
| `/instructor/{instructorId}/reviews` | Instructor reviews |

> New/refactored routes must follow the **Unpredictable Resource Identifiers** and **Route Binding and Object Access Security** rules below — public keys are non-predictable (uuid/public_id), never the auto-increment `id` or a name-derived slug.

---

## Security First — STRICT

**Rule:** Security must be prioritized in every build, change, action, experiment, prototype, refactor, trial feature, admin tool, automation, and integration — even if the work is temporary, internal-only, test-only, or "just for now".

There is no "unsafe because it is only a trial" exception.

### Default mindset
Every task must be approached as if it could eventually reach production or be reused later. Temporary code often becomes permanent code, so trial implementations must still follow secure-by-default practices.

### Non-negotiable principles
- Prefer **secure by default** behavior
- Prefer **least privilege**
- Prefer **deny by default** over open by default
- Validate all inputs
- Authorize every sensitive action
- Escape/output safely
- Protect uploads and file handling
- Avoid secret leakage
- Avoid trust in client-side claims
- Avoid "we will secure it later" shortcuts

### Applies to all work
This includes:
- UI features
- backend logic
- AJAX endpoints
- admin tools
- MCP tools
- mobile/WebView features
- imports/exports
- file uploads
- notifications
- realtime events
- internal scripts
- demo/trial utilities
- test helpers when they affect real environments or real data

### Forbidden thinking
Do NOT justify insecure choices with:
- "it is only a prototype"
- "it is just for testing"
- "only admins will use it"
- "this is internal"
- "we will harden it later"
- "it is only temporary"

These are not acceptable reasons to weaken security.

### Security priority rule
If a feature improves speed, convenience, or visual polish but weakens security, security wins.

### Security is always required
Security is a release requirement, a trial requirement, and a development requirement — not a final polishing step.

---

## Threat-Driven Security — STRICT

**Rule:** Every build, feature, refactor, script, integration, API endpoint, mobile capability, admin tool, trial implementation, and UI action must be designed with active defense against real-world attack classes — both classic and modern.

Do not build as if the system will only be used by honest users. Assume attackers may try to:
- gain unauthorized access
- extract data
- escalate privileges
- abuse APIs
- automate attacks
- inject malicious input
- upload dangerous files
- exploit weak defaults
- flood the system with traffic or expensive requests
- abuse mobile/browser capabilities
- exploit realtime channels
- exploit third-party packages or integrations
- chain small weaknesses into a larger compromise

The system must be designed **secure by default**, **deny by default**, and with **defense in depth**.

### Current threat reality
Claude must account for both classic and modern attack patterns.

This includes:
- OWASP Top 10 web risks
- OWASP API risks
- credential stuffing
- bot-driven abuse
- scraping and mass extraction
- supply chain compromise
- application-layer DDoS
- short-burst high-intensity attacks
- abuse of unrestricted resource consumption
- insecure business flow exposure

Build with awareness that attackers increasingly automate and scale attacks rather than relying only on one-off manual exploits.

---

## Security Coverage Requirements — STRICT

Claude must actively design and review every change against at least the following attack classes and failure modes.

### 1. Access control and privilege escalation
Protect against:
- broken access control
- insecure direct object references
- broken object-level authorization
- broken function-level authorization
- tenant boundary bypass
- role / permission bypass
- privilege escalation
- forced browsing to admin/member/private routes

Required defenses:
- enforce authorization server-side on every sensitive action
- never trust hidden inputs, client-side role flags, or UI visibility as security
- always scope queries by tenant / club / user as appropriate
- deny access by default unless explicitly allowed

### 2. Injection attacks
Protect against:
- SQL injection
- command injection
- template injection
- NoSQL-style injection patterns where relevant
- log injection
- header injection

Required defenses:
- use parameterized queries / Eloquent / query builder safely
- never concatenate raw untrusted input into SQL, shell commands, headers, or HTML
- validate and normalize all input server-side
- review any raw expressions, raw queries, shell calls, and dynamic filters carefully

### 3. Cross-site scripting and content injection
Protect against:
- stored XSS
- reflected XSS
- DOM XSS
- HTML/script injection through rich fields, uploads, URLs, or profile data

Required defenses:
- escape output by default
- do not render untrusted HTML unless explicitly sanitized
- treat file names, captions, notes, bios, links, and metadata as untrusted
- validate URLs and unsafe schemes
- reject dangerous upload types where appropriate

### 4. Authentication, sessions, and account abuse
Protect against:
- weak authentication flows
- session fixation
- insecure remember-me patterns
- brute force
- credential stuffing
- account takeover
- password reset abuse
- enumeration via login/forgot-password behavior

Required defenses:
- use Laravel's secure auth features properly
- throttle auth-sensitive routes
- support strong verification and optional MFA where applicable
- avoid leaking whether an account exists through messages or timing where practical
- enforce secure session and token handling

### 5. CSRF and unsafe state changes
Protect against:
- CSRF on web routes
- unsafe GET requests changing state
- AJAX actions missing proper protection

Required defenses:
- keep CSRF protections intact on state-changing web requests
- never move write behavior onto unsafe methods
- ensure AJAX write flows still use proper Laravel protections

### 6. File upload and media attacks
Protect against:
- malicious file uploads
- fake MIME / fake extension uploads
- SVG/script payloads
- path traversal
- oversized file abuse
- storage poisoning
- orphaned sensitive files
- public exposure of private uploads

Required defenses:
- validate real file bytes, not only client-declared types
- reject dangerous formats when not explicitly needed
- generate safe server-side file names and paths
- never trust user-provided paths or extensions
- delete/replace files safely
- keep sensitive files on the correct disk and visibility level

> See **Image Uploads Must Validate Real Bytes**, **Upload Storage Structure and File Naming**, and **Delete Files Before Records** for the enforced implementation.

### 7. API abuse and data extraction
Protect against:
- broken API authorization
- mass assignment style mistakes
- overexposed JSON responses
- excessive data disclosure
- object enumeration
- unrestricted resource consumption
- business-flow abuse
- shadow / forgotten endpoints

Required defenses:
- return only required fields
- scope every API query tightly
- rate-limit and paginate expensive endpoints
- protect object lookup routes carefully
- document and audit API surfaces
- treat internal/admin APIs as attack surfaces too

### 8. Business logic abuse
Protect against:
- abusing workflows in unintended order
- replaying actions
- duplicate submissions
- bypassing approvals
- price / amount / status tampering
- abusing invitations, registrations, subscriptions, notifications, or scheduling logic

Required defenses:
- validate state transitions server-side
- do not trust client-calculated amounts, statuses, or permissions
- enforce invariants in services/controllers/models
- protect important actions with idempotency or duplicate-submission handling where relevant

### 9. Security misconfiguration
Protect against:
- unsafe debug settings
- permissive storage exposure
- weak CORS / cookie / session config
- verbose errors
- stale permissions
- unprotected admin surfaces
- insecure defaults in trial code

Required defenses:
- prefer secure defaults everywhere
- fail safely when configuration is missing
- do not expose stack traces, secrets, internal paths, or sensitive config in responses
- keep trial and demo features behind proper protections too

### 10. Cryptographic and secret-handling failures
Protect against:
- plaintext secrets
- weak hashing/storage of credentials
- insecure token exposure
- insecure reset / verification flows
- leaking keys in logs, JS, responses, or repositories

Required defenses:
- use framework-approved secure primitives
- never invent custom crypto
- never hardcode secrets in code or client-side output
- minimize secret exposure in logs and debugging

### 11. Realtime, websocket, and event abuse
Protect against:
- publishing data to wrong users
- insecure channel fanout
- event spoofing assumptions
- leaking private payloads in notifications or realtime updates

Required defenses:
- scope every push payload to the intended audience
- send the minimum necessary data
- never assume the client should see data just because it can receive events
- use refresh signals instead of oversharing payloads when views differ per user

### 12. Mobile / WebView / device capability abuse
Protect against:
- unsafe camera/mic/geolocation usage
- permission misuse
- insecure file selection flows
- leaking sensitive data via mobile-only behaviors
- WebView-specific attack surface

Required defenses:
- request only necessary permissions
- feature-detect support first
- provide safe fallbacks
- avoid browser/WebView behaviors that expose unsafe download or popup paths
- treat mobile capability access as security-sensitive, not only UX-sensitive

### 13. Bot abuse, scraping, and automated attacks
Protect against:
- scraping
- credential stuffing
- fake account creation
- abusive form submissions
- endpoint hammering
- inventory / seat / availability abuse
- API probing

Required defenses:
- throttle sensitive routes
- rate-limit expensive reads and writes
- monitor repeated suspicious patterns
- make enumeration and bulk extraction harder
- design endpoints and pagination with abuse resistance in mind

### 14. DDoS and resource exhaustion resilience
Protect against:
- volumetric DDoS
- application-layer DDoS
- burst traffic floods
- expensive query abuse
- oversized upload abuse
- queue flooding
- notification storming
- high-cost search/filter abuse

Required defenses:
- keep endpoints efficient
- rate-limit and cap expensive operations
- paginate large responses
- debounce live search on the client when appropriate
- avoid N+1 and unbounded queries
- reject abusive payload sizes
- design with graceful degradation in mind
- never create endpoints that are cheap to call but expensive to execute without protection

### 15. Supply chain and dependency risks
Protect against:
- vulnerable packages
- unreviewed libraries
- unsafe third-party scripts
- stale dependencies
- compromised update paths
- package drift between builds

Required defenses:
- avoid unnecessary dependencies
- prefer trusted, maintained libraries only when truly needed
- do not add libraries for trivial features
- review security implications before introducing any package or CDN dependency

### 16. Logging, monitoring, and incident visibility failures
Protect against:
- silent failures
- undetected abuse
- missing audit trails
- logs leaking secrets or personal data

Required defenses:
- log security-relevant actions appropriately
- avoid logging secrets, tokens, raw sensitive payloads, or private files
- preserve useful audit trails for sensitive admin/member actions
- make suspicious failure patterns observable without leaking internals to users

---

## Security Checklist for Every Change — STRICT

For every build or action, Claude must actively check:

1. **Input validation**
   - Are request fields validated server-side?
   - Are enums, IDs, and uploaded values constrained?
   - Are untrusted values normalized before use?

2. **Authorization**
   - Is the user allowed to perform this action?
   - Is tenant / club scope enforced?
   - Is sensitive data limited to only authorized viewers?

3. **Output safety**
   - Is rendered output escaped correctly?
   - Is any HTML injection possible?
   - Are JSON responses safe and minimal?

4. **File / upload safety**
   - Are uploaded files validated by real bytes / MIME, not only extension?
   - Are dangerous file types rejected?
   - Are old files deleted safely when replaced or removed?

5. **Secrets / sensitive data**
   - Are secrets kept out of source, responses, logs, and client-side code?
   - Are tokens, keys, internal paths, and private implementation details protected?

6. **Client trust**
   - Is the server avoiding trust in client-side claims, hidden inputs, JS flags, or user-supplied roles/prices/statuses?

7. **Dependency safety**
   - Is a new dependency truly necessary?
   - Does the feature avoid unnecessary external libraries or unsafe packages?

8. **Safe defaults**
   - If configuration is missing or ambiguous, does the feature fail safely?
   - Are dangerous actions protected by explicit checks?

9. **Realtime / background effects**
   - Are MQTT events scoped correctly?
   - Are notifications or broadcasts leaking data to the wrong users?

10. **Trial / internal tools**
   - Even if temporary, could this code expose data, permissions, files, or attack surface later?

---

## Unpredictable Resource Identifiers — STRICT

**Rule:** Never use predictable, enumerable, guessable, or easily-derived public links for user profiles, records, files, resources, or private application objects.

Do NOT expose routes like:
- `/me/ghassanyusuf`
- `/me/profile/25`
- `/member/26`
- `/invoice/1001`
- `/order/501`
- or any other route where changing a name, slug, username, sequence number, or nearby value could reveal another resource

### Why
Predictable identifiers make enumeration easier and increase the risk of:
- insecure direct object reference (IDOR)
- unauthorized profile access
- private data extraction
- tenant boundary probing
- account discovery
- scraping and automated enumeration
- leaking object count, creation order, or naming patterns

### Required rule
Use non-predictable public identifiers for any user-facing or externally reachable resource that refers to sensitive, user-owned, tenant-owned, or permission-protected data.

Preferred options:
- UUIDv4
- strong random tokens
- other high-entropy non-guessable identifiers approved by the project

Avoid for sensitive/public resource URLs:
- incremental numeric IDs
- usernames as primary object keys
- slugs derived only from human-readable names
- email-like identifiers
- any identifier that reveals business structure, ordering, or identity unnecessarily

### Important
Unpredictable identifiers are **defense in depth**, not the main defense.
Every request must still enforce full server-side authorization and tenant/user ownership checks.
A random identifier does NOT replace authorization.

---

## Route Binding and Object Access Security — STRICT

When exposing records through routes, APIs, AJAX endpoints, or realtime-related fetches:

### Required rules
1. Never use the auto-increment database `id` as the public route key for sensitive resources.
2. Never rely on usernames, display names, or simple slugs as the only access key for protected resources.
3. Use a dedicated public identifier column such as:
   - `uuid`
   - `public_id`
   - `external_id`
4. Route model binding for protected resources should use the non-predictable public identifier, not the numeric primary key.
5. Server-side queries must still be scoped by the authenticated user's permissions, ownership, club, tenant, family relationship, or admin scope as appropriate.
6. If the current user should only access their own resource, prefer resolving from the authenticated session/user context instead of trusting a URL parameter at all.
7. Do not leak whether another valid object exists through different error messages, timing, or response details where avoidable.

### Preferred mindset
- internal DB identity may remain numeric if desired
- public-facing identity must be non-predictable where exposure would be sensitive
- authorization is mandatory even when the identifier is random

---

## Anti-Enumeration and Anti-Extraction — STRICT

Claude must build routes, lookups, and APIs to resist enumeration and data extraction attempts.

### Required protections
- avoid sequential public identifiers for sensitive records
- throttle repeated lookup attempts where appropriate
- log suspicious repeated lookup failures or scanning patterns
- return safe generic not-found / unauthorized behavior where appropriate
- do not expose whether nearby identifiers exist
- paginate and scope list endpoints tightly
- never allow a user to switch a parameter and browse other users' data

### Test requirement
Whenever a route or endpoint references an object, test at least this scenario:
- User A tries to access User B's object by changing the identifier
- access must be denied even if the identifier is valid
- access must also be denied whether the identifier is predictable or random

If a resource is private, sensitive, tenant-scoped, or user-owned, assume attackers will try to guess, iterate, scrape, or fuzz its identifier. Design the route and authorization model accordingly.

---

## Mobile / Desktop Separation — STRICT

**Rule:** Any view that has both a mobile and a desktop experience MUST be implemented as **two separate Blade files**, never a single file branching with responsive classes for fundamentally different layouts.

- Keep desktop and mobile markup in distinct files so editing one can never break the other.
- Convention: place them under device-named subfolders, e.g.
  - `resources/views/<feature>/desktop/<view>.blade.php`
  - `resources/views/<feature>/mobile/<view>.blade.php`
  - and, when needed, separate layouts `layouts/<name>-desktop.blade.php` / `layouts/<name>-mobile.blade.php`.
- The controller (or layout) selects the file using the shared `$isMobile` flag provided by the `DetectDevice` middleware: `view($isMobile ? '<feature>.mobile.<view>' : '<feature>.desktop.<view>')`.
- This rule applies to **new** views going forward (e.g. the Business/Chain experience). Existing CSS-responsive pages are not required to be split unless explicitly requested.

> Note: ordinary minor responsive tweaks (a header that stacks via `flex-col sm:flex-row`) do NOT require a separate file — only views whose mobile and desktop layouts genuinely diverge.

---

## Android App (APK) Mirrors the Mobile Web — STRICT

**The mobile web experience IS the Android app.** There is a native Android app in `mobile/`
(Capacitor shell, app ID `bh.takeone.app`) that loads the live site (`https://takeone.bh`)
in a WebView. It is **not** a separate codebase — whatever renders in the mobile web views
is exactly what users see inside the APK. Therefore, whenever working on the mobile app, the
web and the APK must be treated as **one deliverable**.

### Rules
1. **Mobile web changes flow to the app automatically — no rebuild needed.** Any change to a mobile Blade view, controller, route, JS, CSS, or backend logic appears in the installed APK the moment it's deployed, because the app loads the live site. Do **not** tell the user to rebuild the APK for content/UI/logic changes.
2. **Every mobile feature must actually work inside a WebView.** When building a mobile feature, verify it functions in the Android WebView, not just a desktop browser. In particular:
   - Camera / QR scanning / photo capture use `getUserMedia` — the `CAMERA` permission is already declared in `mobile/android/app/src/main/AndroidManifest.xml`. If a new feature needs another device capability (mic, geolocation beyond what's declared, notifications), **add the matching Android permission there** as part of the same task.
   - Avoid browser-only APIs that Android WebView blocks or handles differently (e.g. certain downloads, `window.open` popups, native file pickers) without a WebView-safe fallback.
3. **Only rebuild/re-sign the APK when the NATIVE shell changes** — app icon, name, splash, colors, permissions, Capacitor plugins, or the `server.url`. In that case, do the full loop as one unit: edit config → `npx cap sync android` → rebuild (`npm run build:release`) → re-verify the signed artifact. See `mobile/README.md`.
4. **Keep the shell config in sync with reality.** If the production URL, app name, or brand color changes on the web side, update `mobile/capacitor.config.json` (and re-sync) in the same change — never let the app point at a stale URL or show stale branding.
5. **Never break a mobile view in a way that only shows up in the app.** Since the APK has no browser chrome (no address bar, no easy refresh), a mobile view that soft-locks or clips off-screen is worse in the app than on web. The existing mobile-forms/safe-area rules below apply doubly here.

> Practical summary: **mobile web work = app work.** Build mobile features so they work in the WebView, add any needed native permission alongside, and only touch `mobile/` + rebuild when the native shell itself changes. Full setup, build, and signing details live in `mobile/README.md`.

---

## Component-First Architecture — STRICT

**Rule:** Everything built in this project must follow a reusable component-first architecture. Before creating any new UI block, script, interaction pattern, or layout fragment, first check whether the same need is already covered by an existing component, partial, helper, widget, or view pattern.

### Decision order
Before creating anything new, always evaluate in this order:
1. **Reuse existing component as-is**
2. **Reuse existing component with props / slots / options**
3. **Extend an existing component without breaking current usage**
4. **Compose multiple existing components together**
5. **Create a new reusable component only if none of the above solves the need cleanly**

### Rules
- Do not duplicate markup, JS behaviors, or visual patterns if an equivalent already exists.
- Prefer shared Blade components, Alpine widgets, partials, and reusable JS helpers over page-local one-off implementations.
- New components must be generic enough for future reuse unless the requirement is truly one-off.
- If a new reusable component is created, add it to the **Blade Component Library — REUSE FIRST** section immediately.
- Keep business logic outside presentational components whenever possible.
- Prefer consistency and reuse over cleverness.

### Required mindset
Claude must continuously ask:
- Do we already have this?
- Can an existing component handle this with props?
- Can I extend the existing component safely?
- Should this become a shared component instead of page-only code?

The default answer should be reuse first, extension second, creation last.

---

## Standalone Self-Contained Components — STRICT

**Rule:** Every reusable component must be as standalone and self-contained as possible so it can be moved, reused, or rendered in another place with minimal or no extra wiring.

A component must not depend on hidden page-specific code, fragile global assumptions, or unrelated surrounding markup in order to work.

### What "standalone" means in this project
Each component should include or clearly own:
- its own markup structure
- its own local behavior
- its own internal state handling when applicable
- its own event binding logic
- its own validation or guard logic when applicable
- its own render/update logic when applicable

A component should work when dropped into another compatible view without needing custom rewrites.

### What a component must NOT rely on
Do not create components that only work because:
- another page script happens to initialize them
- a random global variable exists
- a sibling element elsewhere in the page is required
- a hidden inline script in another file does the real work
- a page-specific DOM structure is silently assumed
- undocumented manual setup is required every time

If a component needs setup, that setup must be part of the component itself or explicitly documented as a formal input/API of the component.

### Allowed shared foundations
Standalone does **not** mean duplicating the entire framework stack inside every component.

Components may rely on the project's approved shared foundations only:
- Laravel / Blade
- Tailwind design tokens and utility classes
- Alpine.js
- jQuery
- approved shared helper utilities already established in the project
- approved shell behaviors already part of the application

But they must not rely on hidden page-specific glue code outside their contract.

### Required design standard
A reusable component should ideally be:
- plug-and-play
- self-initializing or explicitly initializable
- configurable through props / data attributes / slots / options
- isolated in behavior
- safe to reuse in multiple places
- predictable in required inputs and outputs

### Preferred implementation style
- If a component needs JavaScript, keep that behavior inside the component file or in a clearly named dedicated companion script/module.
- If a component dispatches or listens to events, use clear event names and document them in the component library section.
- If a component needs IDs, generate or require stable IDs explicitly.
- If a component needs AJAX, it should own its request flow and DOM patch behavior, or expose a clearly defined API for it.
- If a component needs animation, the animation must belong to that component and not depend on unrelated page code.

### Reuse test
Before considering a component complete, ask:
1. Can this be used in another page without rewriting its internals?
2. Does it depend on any hidden external script or page-only structure?
3. Are its inputs and outputs clear?
4. Can it initialize itself safely?
5. Will it behave consistently wherever it is reused?

If the answer is no, the component is not yet standalone enough.

A component must never require mystery code from elsewhere to function. If it depends on something, that dependency must be part of the approved shared foundation or explicitly declared in the component's contract.

---

## Frontend Technology Decision Rules — STRICT

This project uses multiple frontend patterns intentionally:
- Laravel Blade
- Tailwind CSS
- Alpine.js
- jQuery
- AJAX / fetch
- Chart.js
- Select2
- React (when explicitly justified)

### Default rule
Prefer the simplest technology that fits the task while preserving performance, maintainability, and harmony with the existing codebase.

### Use Blade when
- rendering server-driven page structure
- building reusable layout/view components
- rendering initial page state
- the feature is mostly static or form-based
- the UI belongs naturally in the server-rendered flow

### Use Alpine when
- toggling dropdowns, modals, tabs, sheets, accordions, or inline UI state
- the interaction is local to one rendered region
- a lightweight declarative behavior is enough

### Use jQuery + AJAX when
- updating existing Laravel views in place
- submitting forms without reload
- patching DOM regions after API responses
- integrating with existing project scripts and legacy patterns
- working inside admin/member views that already use this pattern

### Use React only when
- the feature is truly state-heavy, highly interactive, or component-driven
- the interaction complexity would be awkward or brittle in Blade + Alpine + jQuery
- the feature benefits from isolated client-side state management
- it can be introduced without fragmenting the product experience

### React constraints
- Do not introduce React casually for simple CRUD/forms/modals that fit the existing stack
- Do not create a parallel frontend architecture unless explicitly requested
- Any React usage must still match the same design system, routes, API contracts, and no-reload rules

### Universal rule
Regardless of technology:
- no unnecessary page refreshes
- in-place updates after writes
- reusable components first
- same product feel across all views

---

## Optimization, Speed, and Harmony — STRICT

**Rule:** Claude must optimize for speed, harmony, and maintainability from the start, not as a later cleanup step.

### Priorities
1. Speed
2. Harmony
3. Reuse
4. Maintainability
5. Clarity
6. Scalability

### Speed rules
- Prefer in-place updates over reloads
- Minimize unnecessary DOM work
- Reuse existing components instead of duplicating heavy markup
- Use partial rendering for complex sections when needed
- Debounce live search inputs when appropriate
- Throttle expensive listeners when appropriate
- Keep animations lightweight and performant
- Avoid large page-specific monolithic scripts when smaller reusable modules will do

### Harmony rules
The platform must feel like one unified product.
That means:
- same visual language
- same spacing rhythm
- same interaction logic
- same component behaviors
- same naming conventions
- same UX expectations across pages

### Avoid
- duplicate components solving the same problem
- duplicate JS behaviors with slightly different naming
- inconsistent UI patterns for the same action
- unnecessary framework mixing
- animations that reduce responsiveness
- refactors that reduce consistency with established project patterns

---

## Mobile Device Capabilities and Animation — STRICT

Claude must be comfortable building mobile-web features that use supported browser / WebView device capabilities when required, such as:
- camera
- microphone
- geolocation
- file picking / image capture
- share actions
- vibration / haptic-like feedback where supported

### Rules
- always detect capability support first
- always request permission correctly
- always provide fallback behavior if unsupported
- always ensure the feature still works inside the Android WebView shell

Claude must also be comfortable building polished UI animation using:
- Tailwind transitions
- Alpine transitions
- CSS transitions / keyframes
- lightweight JS-driven animation when necessary

Animations must improve clarity and feel, never reduce speed or usability. Respect `prefers-reduced-motion` where appropriate.

---

## Design Rules — STRICT

### 1. Never modify existing UI design
Do not change existing layout, styling, colors, or visual structure unless **explicitly instructed** to do so. When adding features, reuse existing HTML structure and class patterns exactly. Do not introduce new visual elements, color changes, or layout shifts as a side effect of logic changes.

### 2. Reuse existing patterns
When adding new UI, match the exact patterns already used in the same view — same button styles, same card structure, same spacing classes. No speculative improvements.

### 3. No unsolicited refactors
Do not clean up surrounding code, add docstrings, rename variables, or reorganize logic unless specifically asked.

### 4. No native OS-rendered form popups (`<select>`, `<input type="date|time">`) for styled UI
Never use a native `<select>`/`<option>`, or a native `<input type="date">` / `type="time"` / `type="datetime-local"`, when the control is part of the styled UI. The OS renders these popups (the option list, the calendar/clock) with sharp corners and its own colors — they cannot be themed and look inconsistent with the design system. Build a custom **Alpine** control instead: a `rounded-xl` bordered trigger button (chevron that rotates on open, purple focus ring) plus an absolutely-positioned `rounded-xl` panel (`bg-white border border-gray-100 shadow-lg overflow-hidden`) with a fade/slide `x-transition`, `hover:bg-muted/60` rows, and a `text-primary` highlight (check / selected state). Close on `@click.outside` and `@keydown.escape`. Keep the panel as smooth/rounded as the trigger. Reuse an existing `<x-*-dropdown>` component when one fits (see the Blade Component Library); otherwise match the reference implementations in `resources/views/personal/challenge-create.blade.php` — the **Win condition / Stake** dropdowns and the **Deadline** calendar popover (stores an ISO `YYYY-MM-DD` string, disables past days, has Clear / Today actions).

### 5. Logos render on transparent backgrounds — never on a white tile
Club logos, brand logos, and business/chain logos are supplied as transparent PNGs and MUST be shown as the bare image on a transparent background. Never wrap a logo in a white/filled rounded card, tile, or "chip" (`bg-white`, `p-1`, `shadow`, `ring`, `rounded-2xl overflow-hidden`) — that white square looks broken against non-white/hero/photo backdrops. Use only a sizing container plus the image: `<span class="w-16 h-16 flex-shrink-0"><img class="w-full h-full object-contain" ...></span>`. No background fill, no padding box, no ring/shadow behind the mark. This applies to every logo placement (public club page hero, cards, headers, nav, feed avatars where a real logo is used).

---

## No Page Reload Rule — STRICT

**All write operations (create, update, delete) in this project must update the UI in place. Never require the user to manually refresh the page to see the result of their action.**

### How to implement:

1. **Every AJAX write endpoint** must return the updated data in its JSON response alongside `success` and `message`:
   ```json
   { "success": true, "message": "...", "<entity>": { ...updated fields... } }
   ```

2. **Every modal / form that submits via AJAX** must, on success, dispatch a `CustomEvent` carrying the returned data so any listening component on the page can update itself:
   ```js
   window.dispatchEvent(new CustomEvent('entity-updated', { detail: data.entity }));
   ```

3. **The page/view** that displays the data must listen for the event and patch the relevant DOM elements **in place** — no `window.location.reload()`, no `location.href =` redirect unless the action itself requires navigation (e.g. creation that navigates to a new record).

4. **Add stable target IDs** (`id="..."`) to every element whose content can change after a write so the JS listener can find and update it reliably.

5. For **complex re-rendered sections** (lists, cards with conditional content), regenerate the innerHTML from the returned JSON rather than patching individual text nodes.

> Returned payloads are subject to the security rules above — return only fields the viewer is authorized to see, never internal ids/paths/flags they shouldn't have.

### Established pattern (member profile page):
- Server returns `member` object in update response
- `submitForm()` in profile modal dispatches `member-profile-updated` with the member data
- `show.blade.php` has a `window.addEventListener('member-profile-updated', ...)` listener that patches: name, motto, age, blood type, marital status, social links, emergency contacts, documents, health conditions — all without reload

Apply this same pattern to every other feature: health records, goals, affiliations, tournaments, club details, packages, instructors, etc.

---

## Realtime / MQTT — Always Instant — STRICT

**Every action must reflect instantly for *all* affected users — never make anyone refresh.** The actor's own device updates in place (see No Page Reload Rule); everyone *else* affected must be updated live over MQTT. Notifications must arrive the same way.

### Rules
1. **On every write that affects other users, push over MQTT in the same request.** Use `Realtime()->publishToUser($id, $channel, $payload)` (one user) or `Realtime()->publishMany([...])` (many). It's best-effort — the DB stays the source of truth — but it must always be attempted. Realtime is enabled (`REALTIME_ENABLED=true`).
2. **Notifications go through `UserNotification::notifyUser()`**, which already writes the row *and* pushes MQTT (`notifications` channel) with an `action_url` deep-link. Never create notification rows without it.
3. **Client patches in place.** `realtime.js` re-emits each inbound message as a DOM event `realtime:<channel>` (e.g. `realtime:notification`, `realtime:message`, `realtime:schedule`). Feature views add a listener that updates the DOM — no reload.
4. **Use an `{action: '...'}` discriminator per channel.** Two reliable shapes:
   - **Targeted patch** — send the changed entity (`{action:'created'|'updated'|'deleted', <entity>:{...}}`); the listener upserts/removes that one item. Best when the payload is identical for every recipient.
   - **Refresh signal** — send `{action:'refresh'}` and have the view silently re-fetch its data from a JSON endpoint and re-render. **Use this when the same change renders differently per user** (different ids/content/permissions), e.g. a club class where each user sees their own card variant. Don't try to hand-craft per-user card payloads.
5. **Fan out to the whole audience**, not just the obvious user. A class change touches enrolled members + the coach + substitute(s) + the actor — push to all of them.
6. **Dedup client listeners** that live inside shell-swapped content: the mobile shell re-runs inline scripts on every AJAX nav, so store the handler on `window.__xxx` and `removeEventListener` the previous one before re-adding, or listeners stack up.
7. **Scope every payload to its audience** (see Security Coverage §11). Send the minimum necessary data; a recipient must never receive fields they aren't authorized to see. When the same change renders differently per user, use the refresh signal rather than oversharing.

### Reference implementation
`/me/schedule`: `scheduleData()` returns the schedule as JSON; `pushScheduleRefresh($userIds)` publishes `{action:'refresh'}` on the `schedule` channel to every affected user; the list view's `realtime:schedule` handler calls `reloadData()` on `refresh` (and patches individual cards on `created/updated/deleted`). Personal-session edits use the targeted-patch shape; club-class/substitute changes use the refresh shape.

---

## Keep the MCP Server in Sync — STRICT

**Rule:** The general-purpose MCP server (`app/Mcp/*`, exposed at `POST /mcp` and via `php artisan mcp:start takeone`) is a first-class interface to the platform, just like the web UI. **It must never fall behind the data model or feature set.** Whenever a change alters what the app can read or do, the MCP must be updated in the **same** change — treat a stale MCP as a broken build. Full subsystem docs: `Documentation/MCP.md`. See [[project_mcp_server]].

### When a change REQUIRES an MCP update
Update or add a tool (in `app/Mcp/Tools/`, registered in `app/Mcp/Servers/TakeOneServer.php`) whenever you:
- **Add a new entity / feature** that a user or integration would reasonably want to read or act on (new model, new admin action, new member self-service flow) → add a read tool, and a write tool if the app can mutate it.
- **Add or rename a field** that an existing tool returns (e.g. a new member/club attribute) → surface it in that tool's JSON so consumers see it.
- **Change a relationship or query** a tool relies on (e.g. the club↔member relation, a status enum, a slug/uuid binding) → fix the tool's query so it still returns correct, scoped data. (The existing tools already hit real gotchas: `memberClubs()` not `memberships`, `birthdate` not `date_of_birth`, `ClubTransaction.payment_method` is a NOT-NULL enum — keep these correct.)
- **Change an authorization rule** (who can see/do what) → mirror it in `app/Mcp/Concerns/AuthorizesClubAccess.php` (`canAdminClub`, `canViewMember`, `accessibleClubIds`) so the MCP enforces the *same* scope. The MCP must never expose more than the acting user could do in the UI.
- **Add a validation rule / enum vocabulary** to a write path → apply the identical rule in the corresponding write tool (e.g. gender `Male`/`Female`, payment methods).

### How to do it (non-negotiable steps)
1. Add/adjust the tool; extend `App\Mcp\Tools\BaseTool` (gives `guard()`, the write kill-switch, and scoping helpers). Guard first: `$user = $this->guard($request); if ($user instanceof \Laravel\Mcp\Response) return $user;`
2. Set `protected bool $isWrite = true;` on any tool that mutates data.
3. Register the class in `TakeOneServer::$tools`.
4. Enforce tenant scope with the `AuthorizesClubAccess` helpers — never query models unscoped.
5. Add/extend a case in `tests/Feature/McpServerTest.php` covering both the happy path **and** an authorization denial.
6. Update the tool table in `Documentation/MCP.md`.

### What does NOT need an MCP change
Pure UI/styling/copy edits, internal refactors, and changes with no new readable/actionable surface. When unsure, err toward adding the tool — an integration that can't see a feature is worse than one extra tool.

> Rationale: external systems (n8n, Claude, other services) consume the platform through this one server. If a feature ships to the web but not the MCP, every integration silently drifts out of date. The MCP is part of the deliverable, not an afterthought. MCP tools are a full attack surface — the Security sections above apply to them exactly as they do to web routes.

---

## Admin Sidebar SPA Navigation — STRICT

**Rule:** Clicking any link in a desktop admin sidebar (club admin `/admin/club/{club}/*` **and** super-admin platform `/admin/*`) must load the page **in place — no full browser reload**, like a React SPA. The sidebar, top bar, and scroll shell stay mounted; only the main content area swaps.

### How it works
- **Navigator:** `resources/views/partials/admin-shell-nav.blade.php` — included by both desktop admin layouts (`layouts/admin-club.blade.php`, `layouts/admin.blade.php`). It intercepts clicks on `a[data-shell-link]`, `fetch()`es the destination (sends `X-Requested-With`), parses the response, and swaps it in. Shows a slim top progress bar, updates `document.title`, the active nav state, and `history.pushState`/`popstate`. Modeled on the proven mobile shell navigator (`partials/mobile-shell-nav.blade.php`).
- **Swap target:** the layout's `<main>` carries `data-shell-main="club"` (or `"platform"`) plus `data-route`. The navigator replaces its `innerHTML` from the destination's matching `<main>`. **If the destination has a different `data-shell-main` value (or none), it hard-loads** — so club↔platform and admin→non-admin links fall back to a real navigation (their sidebars differ).
- **Page scripts & modals:** pages put JS/modals in `@push('scripts')`/`@push('modals')` (rendered *outside* `<main>`), so `app.blade.php` wraps those stacks in `<div id="shell-scripts">` / `<div id="shell-modals">`. On nav the navigator swaps those regions and runs each **unique** `<script>` **once per session** (deduped by content/`src`, seeded with the first-paint scripts). This is deliberate: desktop admin pages declare top-level `const`/`let`/`function` at global scope (for inline `onclick=`), so re-running the same script would throw "already declared". Globals therefore run once; their functions persist.
- **Re-init bridge:** after each swap the navigator **dispatches a synthetic `DOMContentLoaded`** and a `shell:navigated` window event. Existing pages gate their init on `DOMContentLoaded`, so this re-runs that init against the freshly-swapped DOM — on first visit AND revisit (the listener registered on first visit persists and re-fires). Alpine re-inits swapped `x-data` via its own mutation observer (the navigator does **not** call `Alpine.initTree`, which would double-initialize).
- **Speed:** links are **prefetched on hover / touchstart** (15s cache) so the click is usually instant; a top progress bar covers any remaining latency.
- **Page styles:** `@push('styles')` blocks are injected from the destination's `<head>` on first visit (content-deduped, so globals aren't re-added).
- **Marking links:** add `data-shell-link data-route="{{ $routeName }}"` to a sidebar `<a>` **only when it stays within the same shell**. Never mark cross-shell links (platform↔club, Back to Explore, external/`target="_blank"`) — leave them as plain links so they hard-load.
- **In-content links navigate in place automatically.** Beyond the sidebar, the navigator also intercepts any same-origin `<a>` whose path is under the shell's base (`data-shell-base` on `<main>` — `/admin/club/{slug}` or `/admin`), e.g. status-filter and pagination links. Links to *other* sections (member profiles `/member/*`, etc.) fall through to a normal full load. A non-HTML response (file download, redirect, JSON) auto-falls-back to full navigation via a Content-Type + redirect check.
- **Opt a content link OUT** of in-place nav with `data-no-shell` (e.g. file-download links like the member import template), or `download` / `target="_blank"` / an `onclick` / `data-bs-toggle` (those are skipped automatically).

### What page authors should know
1. **`DOMContentLoaded` works** — the navigator re-fires it on every swap, so existing `document.addEventListener('DOMContentLoaded', …)` init runs on each visit. New init code can use it, or listen for the `shell:navigated` window event. Put DOM-binding init *inside* that handler (not bare top-level) so it re-runs per visit and binds to the new DOM.
2. **Top-level declarations run once** — `const`/`let`/`function`/`class` at script top level execute only on the first visit (dedupe). Don't rely on them re-executing; keep per-visit work inside the `DOMContentLoaded`/`shell:navigated` handler.
3. **Dedup persistent listeners.** A `DOMContentLoaded` handler re-fires every nav, and re-bound `document`/`window` listeners can stack. Prefer delegated listeners, or store the handler on `window.__xxx` and `removeEventListener` the previous one before re-adding (see the mobile-shell dedup note). The navigator itself is guarded with `window.__adminShellNavInit`.

### Mobile shells: cross-shell guard
All mobile shells reuse `id="shell-content"`, so the mobile navigator can't tell them apart by id alone. Each shell's `<main id="shell-content">` carries a **`data-shell-id`** (`personal` / `admin-club` / `business`); the navigator hard-loads when the destination's `data-shell-id` differs (mirrors the desktop `data-shell-main` guard). **When you add a new mobile shell layout, give its `#shell-content` a unique `data-shell-id`.** Cross-shell links must still be plain `<a>` (no `data-shell-link`).

---

## Navigation Integrity — no dead ends — STRICT

**Rule:** Every clickable control must go to a real, reachable destination. A link/button that 404s, opens a modal that isn't on the page, or is a bare `href="#"` with no handler is a defect — treat it like a broken build.

- **`route('name', …)` must name a real route** with the **correct binding key** — `member.show` binds `{uuid}`, but `member.update`/`edit`/`destroy`/`upload-picture` bind `{id}`; club-admin routes bind `{club}` (id-or-slug via `Route::bind('club')`); many public routes need a `{country}` prefix (`/{country}/clubs/{slug}`). Passing an id to a `{uuid}` route (or omitting `{country}`) is a runtime 404, not a compile error — audit these when linking.
- **A modal trigger requires the modal on the page.** `data-bs-toggle="modal" data-bs-target="#x"` needs an element `id="x"` in the rendered output; if the real editor is an Alpine component (e.g. `<x-profile-modal>` opening on the `open-profile-modal` window event), dispatch that event instead — `onclick="window.dispatchEvent(new CustomEvent('open-profile-modal'))"`. Don't point at a `#modalId` that doesn't exist.
- **`data-bs-*` goes through the bridge** (`app.blade.php`), which fires on `data-bs-toggle`. `data-bs-target` **without** `data-bs-toggle` does nothing. `window.bootstrap.Modal` / `bsModal.show()` are shimmed and work.
- **No placeholder `href="#"`** on a control that implies navigation. Wire it to the real route/tab, or don't render it. Prefer omitting an unbuilt action over shipping a dead button.
- **JS-built URLs** (`fetch`/`location.href`/template literals) must match a real route pattern — verify against `php artisan route:list` before committing. Dead/orphaned handler clusters that point at removed routes should be deleted, not left dormant.
- **To verify:** dump the route table (`php artisan route:list --json`) and cross-reference `route('…')` names, then click-test the primary actions on any page you touch.

---

## LOCKED — Do Not Modify: Profile Picture Cropper Config

These values are final and must never be changed:

**`resources/views/components/profile-modal.blade.php`** (`<x-takeone-cropper>` call):
- `width="300"` — viewport crop width
- `height="400"` — viewport crop height
- `shape="rectangle"`
- `:canvasHeight="500"` — canvas work area height (must stay 500, NOT 400)

**`resources/views/vendor/takeone/components/widget.blade.php`** (modal-dialog div):
- Must use: `class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: {{ $modalMaxWidth }}; width: {{ $modalWidth }}px;"`
- Do NOT replace with `.modal-lg` or any class-only approach
- Default params: `$modalMaxWidth = '75%'`, `$modalWidth = 1000`

---

## Payment Gateway

Intentionally **deferred indefinitely** — additional cost not wanted at this stage.

- Do not suggest payment gateway integration (Stripe, Tap, Benefit Pay, etc.)
- The intended workflow is manual: member uploads proof-of-payment → club admin approves
- Do not design features that depend on a payment gateway

---

## Upload Storage Structure and File Naming — STRICT

**Rule:** Every uploaded file must be stored in a clear, organized, entity-based folder structure, and every stored filename must be generated by the application. Never store uploads using their original client filename.

### Folder structure
Uploads must be grouped by:
1. owner type
2. owner identifier
3. feature area
4. optional child record identifier where applicable

Examples:
- `people/{person_public_id}/profile/`
- `people/{person_public_id}/attachments/`
- `people/{person_public_id}/posts/{post_public_id}/`
- `people/{person_public_id}/documents/`
- `clubs/{club_public_id}/logo/`
- `clubs/{club_public_id}/documents/`
- `clubs/{club_public_id}/posts/{post_public_id}/`
- `clubs/{club_public_id}/gallery/`

Do not dump unrelated uploads into shared flat folders.

### File naming
- Never store the original uploaded filename.
- Never store a filename derived from the original name by adding a suffix, prefix, timestamp, or slug.
- Always generate the stored filename on the server.
- Use a random, UUID, ULID, hash-like, or timestamp-plus-random generated filename.
- The extension must be assigned safely by the server based on validated file type rules.
- The original filename may be discarded entirely or stored only as untrusted metadata when explicitly needed, but never used as the storage filename.

### Display title
- The human-readable file title must come from a separate form input and be stored separately in the database.
- The display title is for UI only.
- The physical stored filename is for storage only.
- Never treat the original uploaded filename as the display title automatically unless explicitly required and safely sanitized.

### Security requirements
- Folder paths must be application-generated, never user-controlled.
- Do not allow path traversal or nested user-provided paths.
- Files must be validated by authorization, type, size, and real content rules before storage.
- Sensitive uploads should be stored in private/non-public storage and served through controlled access.
- Publicly accessible files must still follow generated naming and proper access design.

### Required mindset
Storage structure must be readable to developers, but filenames must be non-meaningful to attackers.
Organized folders, random filenames, and separate display titles are mandatory.

---

## Delete Files Before Records — STRICT

**Rule:** Whenever a record owns uploaded files (proof images, photos, documents, attachments, media), the **files MUST be deleted FIRST, then the record**. Never delete a row and leave its uploaded files orphaned in storage. This applies to every delete path — admin actions, cleanup scripts, manual DB maintenance, and bulk wipes.

### How it's enforced in code

- Trait **`App\Traits\DeletesUploadedFiles`** hooks the model's `deleting` event and purges the declared files *before* the row is removed (while the paths are still available). Best-effort: a missing file never blocks the deletion.
- Each file-bearing model declares its uploads + disk:
  ```php
  use App\Traits\DeletesUploadedFiles;

  protected array $fileUploads = [
      'proof_of_payment' => 'local',   // attribute => disk
      'refund_proof'     => 'local',
      'cover_image',                    // bare entry → 'public' disk
  ];
  ```
- On `SoftDeletes` models the trait only purges on a real **force-delete** (a soft-deleted row may be restored).
- Already applied to: `ClubMemberSubscription` (`proof_of_payment`, `refund_proof` on `local`), `Order` (`payment_proof_path` on `public`). **Apply this trait to any new model that stores file paths.**

### Caveats

- **Eloquent events do NOT fire on mass/bulk deletes** (`Model::query()->delete()` / `->forceDelete()`). For bulk cleanups, either iterate (`->cursor()->each(fn ($m) => $m->forceDelete())`) so the trait runs, or delete the files explicitly alongside the rows.
- When clearing financial records by hand, also wipe the proof folders: `storage/app/private/payment-proofs/`, `storage/app/public/order-proofs/`, `storage/app/public/payment-screenshots/` (keep the dirs + `.gitignore`).

---

## Image Uploads Must Validate Real Bytes — STRICT

**Rule:** NEVER derive a stored file's extension from the client-supplied data-URI header, and never write request bytes to disk with `file_put_contents`/raw paths. Every base64 image upload MUST go through the **`App\Traits\StoresBase64Images`** trait, which sniffs the real MIME from the decoded bytes, assigns the extension server-side from a whitelist (jpg/png/gif/webp — **SVG rejected**, it can carry script), and returns the stored path (or `null` to reject).

### Why
The old pattern `$ext = explode('image/', $parts[0])[1]` let a logged-in user upload `shell.php`/`.svg` by lying in the data-URI header → stored XSS, and RCE under the common nginx/php-fpm docroot config. Fixed across all upload endpoints on the `harden/launch-readiness` branch.

### How to add an upload endpoint
```php
use App\Traits\StoresBase64Images;   // add to the controller class

$path = $this->storeBase64Image($request->image, $folder, $filenameBase);
if ($path === null) {
    return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
}
// delete the OLD file only after a successful store, and only if the path changed
```
- Validate the request with **`UploadImageRequest`** (or the same rules): `image` = `starts_with:data:image/`; `folder` = `regex:/^[A-Za-z0-9_\-\/]+$/`; `filename` = `regex:/^[A-Za-z0-9_\-]+$/` (blocks traversal + stray extensions).
- The extension is assigned by the trait — do **not** append `.$ext` yourself, and don't trust `$request->filename` to already carry one.
- The `$folder` must be built by the app from the owning entity's public id per **Upload Storage Structure and File Naming** — never passed through from raw client input.
- Endpoint-level regression coverage lives in `tests/Feature/UploadSecurityTest.php`; the trait's unit coverage is `tests/Feature/StoresBase64ImagesTest.php`. Add a case when you add an endpoint.

---

## Canonical Enum Vocabularies — STRICT

**Gender is stored and validated as `Male` / `Female`** everywhere — never `m`/`f`. This was normalized by migration `2026_06_05_000001_normalize_gender_enum_on_users_table` and is the value the `<x-gender-dropdown>` submits. Every FormRequest/controller uses `in:Male,Female`. When writing tests or new forms, send `Male`/`Female`; sending `m`/`f` will fail validation silently (redirect-back with errors, no row created).

---

## 403s Redirect on Web, Return JSON on API — expected behavior

The global handler in `bootstrap/app.php` intentionally converts a 403:
- **Browser navigation** (non-JSON) for a logged-in user → `redirect('/')` with an error flash (so a stale higher-privilege page never dead-ends on a raw 403). Access is still denied.
- **JSON/AJAX** (`expectsJson`) → real `403`.

So authorization tests must assert `assertRedirect('/')` for a `get()` and `assertForbidden()` for a `getJson()` — a browser GET to a forbidden page is a `302`, not a `403`. Don't "fix" the controller to emit 403 on web; that reroute is deliberate.

### Tenant resolution in `role:`/`permission:` middleware
`CheckRole`/`CheckPermission` resolve the tenant via `resolveTenantId()` (bound `{club}` model → numeric id → `slug` lookup). Do not reintroduce the old inline `$a ?? $b ? c : d` expression — `??` binds tighter than `?:`, so it always resolved by slug and returned `null` under a `{club}` binding, silently defeating any club-scoped role/permission check.

---

## Financials Feature Status

- Step 1 ✅ `PlatformController@joinClub` auto-creates a `ClubTransaction` on package registration
- Step 2 ✅ `getMonthlyFinancials()` uses real DB query — dashboard chart works
- Step 3 ✅ "Cash to Collect" sums `amount_due` from unpaid subscriptions
- Step 4 🔜 Mark subscription as paid from members admin page — **not started**

---

## Demo Data — Super-Admin Showcase (REMOVABLE before go-live)

A large, cohesive demo dataset wired to the super admin (`superadmin@takeone.bh`) so every surface looks full for demos. **It is fully removable** — built specifically so it can be wiped before going live.

- **Seed:** `php artisan demo:seed` (options: `--clubs=6 --members=40 --admin=<email> --fresh`). Creates a business/chain, N mixed-sport clubs (Taekwondo/Boxing/Fitness/Swimming/Padel/Yoga), trainers, packages+activities with weekly class schedules, members + active subscriptions, club feeds, challenges, events, and shop products. Wires the super admin as owner of every club + business, enrolled in 2 (synced `/me/schedule` classes), coaching 1 (teaching classes), plus personal sessions, feed posts/stories/follows, challenge participations, event registrations and a duel.
- **Purge:** `php artisan demo:purge` (`--force` to skip prompt). Removes **exactly** what was seeded.
- **How removal stays exact & safe:** `demo:seed` records every created row id (and any uploaded file) to a **manifest** at `storage/app/private/demo/manifest.json` (`App\Support\DemoManifest`). `demo:purge` reads it, deletes files first (defensively scanning file-bearing columns in case images were uploaded to demo records via the UI), then deletes rows in reverse-FK order inside a transaction. It can never touch real imported members or the super-admin account. Second safety net: demo clubs use slug `demo-*` and demo users `@demo.takeone.bh`.
- Commands: `app/Console/Commands/DemoSeed.php`, `app/Console/Commands/DemoPurge.php`. Only one manifest at a time (re-seed requires purge or `--fresh`).
- Demo/trial utilities are **not** exempt from the Security First rules — they run against the real environment and real storage.

---

## Pre-Launch Runbook — operational, NOT code

These are launch gates that live in the **environment/ops**, not the repo — a green test suite does not clear them. Verify before any production go-live. (Surfaced by the launch-readiness audit; code-level findings from that audit are already fixed and codified in the STRICT sections above.)

- **Production env flags (BLOCKER).** The live `.env` must be `APP_ENV=production` + `APP_DEBUG=false` (it was `local`/`true` on `https://takeone.bh`, which leaks a full stack trace + secrets on every error). Also set `LOG_LEVEL=warning` and `LOG_STACK=single,sentry` (so `Log::error()` reaches Sentry). **Re-run `php artisan config:cache` after ANY `.env` change** — caching freezes `env()`.
- **Warm prod caches on deploy:** `config:cache` + `route:cache` + `event:cache` + `view:cache` — all four now succeed. `view:cache` used to die with `DirectoryNotFoundException` for `vendor/takeone/cropper/src/resources/views`: takeone/cropper's provider registers `__DIR__.'/resources/views'` from `src/`, but ships its views one level up at `resources/views`. `AppServiceProvider::pruneMissingViewPaths()` now drops non-existent paths from every view namespace on `booted()`, so the bad hint is gone before `view:cache` walks it. The published copy at `resources/views/vendor/takeone/` is what resolves at runtime, and still does. **This is a workaround for an upstream package bug** — fix the path in `laravel-image-cropper` and the guard becomes a no-op (keep it; it protects against any package doing the same).
- **Backups / DR (BLOCKER).** Stand up a cron'd, rotated, **off-server** backup of the DB **and** upload folders (`storage/app/public/*`, `storage/app/private/payment-proofs`, chat attachments). Copy the Android release keystore (`mobile/android/app/takeone-release.jks`, git-ignored) to secure external storage — losing it means the Play Store app can never be updated. (`/database/*.sqlite*` is now in `.gitignore` — the DB, its `-wal`/`-shm` sidecars, and `.bak-*` snapshots can never be committed. Nothing sqlite was ever tracked, so no history scrub was needed.)
- **Mail (MAJOR).** Queued mail had failing jobs (`SendQueuedMailable`) — verification emails silently not sending. Diagnose the Gmail SMTP creds, send a real end-to-end verification, then clear `queue:failed`. Mail is real Gmail SMTP (`smtp.gmail.com:465 ssl`), queued — see the no-Mailpit rule.
- **Datastore at scale (MAJOR).** Production is SQLite (WAL). Fine for a soft launch, but SQLite is single-writer — under concurrent multi-club writes it throws "database is locked". A ready `mysql` connection already sits in `config/database.php`; migrate before real concurrency. Keep WAL checkpoints healthy (the `-wal` file should not dwarf the DB).
- **Workers as least-privilege.** Supervisor `queue:work` should run as `www-data`, not `root`.

---

## Technical Notes

- **Storage permissions:** `umask(0002)` set in artisan for group-writable storage
- **Primary color:** 65% lightness (HSL)
- **Bootstrap replacement:** The project uses a custom JS bridge (`app.blade.php`) that handles `data-bs-*` attributes without Bootstrap CSS/JS. Do not add Bootstrap JS or assume Bootstrap modal/tab APIs work natively — they go through this bridge.
- **No npm test framework** — verify UI features manually or via dev server
- **No native browser dialogs** — never use `alert()`, `confirm()`, `prompt()`, or any native browser dialog. Always use `window.showToast(...)` for notifications and custom modals for confirmations.

---

## Mobile Responsiveness

Status: Complete. Key patterns:
- `overflow-x-auto` for tables and tab containers
- `flex-col sm:flex-row` for responsive headers
- `z-40` on sidebar when open (overlay was blocking clicks — resolved in commit `f314f2e`)

---

## Mobile Forms Must Be Mobile-Friendly — STRICT

**Rule:** Any form, modal, or sheet shown in a mobile view MUST be fully usable on a phone — the entire form (including every field AND the submit/cancel actions) must be reachable and visible. Never ship a mobile form where content is clipped off-screen or the submit button can't be reached. This is a default requirement whenever the user is talking about a mobile view — they should not have to ask for it.

**Required patterns:**
- **Use a bottom-sheet (or full-screen) layout** for non-trivial mobile forms: `fixed inset-x-0 bottom-0 max-h-[92vh] flex flex-col`, with a `flex-shrink-0` header, a `flex-1 overflow-y-auto` scrollable body, and a `flex-shrink-0` sticky footer holding the actions. The body scrolls; the actions stay reachable.
- **Respect the safe area** on the sticky footer: `padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));`. ⚠️ This only works because the base layouts (`layouts/app.blade.php`, `layouts/tailwind.blade.php`) set `<meta name="viewport" content="… viewport-fit=cover">` — without `viewport-fit=cover` every `env(safe-area-inset-*)` resolves to `0` and the padding silently does nothing. Never drop `viewport-fit=cover` from a base layout's viewport meta, and never ship a mobile viewport with `maximum-scale=1`/`user-scalable=no` (breaks pinch-zoom / WCAG 1.4.4).
- **⚠️ Teleport fixed overlays to `<body>`.** The mobile shell's `#shell-content` carries `.mobile-stagger`, whose `m-rise` animation leaves a `transform` on every direct child. A CSS `transform` makes that element the containing block for any `position: fixed` descendant — so a bottom-sheet's `bottom-0` / `max-h-[92vh]` resolve against the tiny wrapper instead of the viewport and the form gets clipped. Wrap any fixed sheet/FAB/overlay in `<template x-teleport="body">…</template>` (inside an Alpine `x-data` scope) so it escapes the transformed ancestor. Use `z-[60]+` for teleported sheets so they sit above the bottom tab bar (`z-40`).
- Inputs full-width (`w-full`), comfortable tap targets, and the design-system input/button tokens. Keep [[Mobile = Creative + Animated]] in mind — animated, on-palette, not a stripped-down desktop form.

> Reference implementation: `resources/views/components/schedule-session-modal.blade.php` (teleported bottom-sheet with scrollable body + sticky footer).

---

## One Cropper Everywhere — Mobile Uses `<x-takeone-cropper :inline>` — STRICT

**Rule:** Every image crop/upload in a **mobile** view MUST use the shared `<x-takeone-cropper>` widget in **inline mode** (`:inline="true"`) — the mobile-first bottom-sheet crop editor. Never render the cropper's default **modal mode** on mobile, and never build a one-off/alternate cropper. There is exactly one cropper in this project; on phones it always appears as the same bottom sheet.

### Why
The default modal mode opens through the Bootstrap bridge with the LOCKED desktop dialog size (`max-width:75%; width:1000px`), which collapses to a cramped, clipped box on a phone. Inline mode is the purpose-built mobile bottom sheet: teleported to `<body>` (escapes the mobile-shell transform trap), scrollable body, safe-area sticky footer, delegated handlers, and an auto-fit crop viewport. It is already used by the profile-picture and photo-edit croppers.

### How (canonical mobile invocation)
```blade
<x-takeone-cropper
    id="…Mobile" mode="ajax" :inline="true"
    :width="1600" :height="900" shape="rectangle" :canvasHeight="300"
    folder="…" filename="…" :uploadUrl="route('…')"
    sheetMaxWidth="100%"                                   {{-- match the host form's full-width sheet --}}
    sheetClass="rounded-t-3xl shadow-2xl bg-background"    {{-- match the host sheet's shape/size --}}
    :showControls="false"                                  {{-- drop sliders; pinch/twist gestures drive it --}}
    :showCancel="false"                                    {{-- the header ✕ closes; no redundant Cancel --}}
    saveText="Crop" :uploadAsIs="true" uploadAsIsText="Upload" />
```
- **Match the host sheet.** Set `sheetMaxWidth` / `sheetClass` so the crop sheet looks the same (shape + size) as the form it opens from. Defaults keep the old centered-card look for non-mobile callers, so overriding is opt-in and never touches the profile/photo croppers.
- **Sliders vs gestures.** `:showControls="false"` hides the zoom/rotation sliders; the widget then enables **pinch-to-zoom + two-finger twist-to-rotate** (Cropme has no native pinch — the widget adds it). Single-finger pan is Cropme's. Leave `showControls` default (`true`) only where sliders are actually wanted.
- **Labels / actions.** Customize with `saveText`, `uploadAsIsText`; hide the footer Cancel with `:showCancel="false"` (there is always a header ✕).

### Non-negotiable gotchas (already handled inside the widget — do not regress)
- **All handlers are delegated off `document`** (namespaced `.tk{id}`, guarded by `off()`), because in mobile the cropper renders inside a `<template x-teleport="body">` and `$(ready)` fires before Alpine teleports — direct `$('#id').on()` / `getElementById(...).addEventListener` silently bind to nothing and the picked image never loads. Never convert these back to direct binds.
- **The crop element is resolved lazily inside `initCropper`** (not cached at `$(ready)`), for the same teleport reason.
- **The viewport auto-fits the canvas.** Cropme throws *"Viewport height cannot be greater that container height"* if `width`/`height` exceed the `canvasHeight` box — the widget scales the viewport down (preserving aspect) only when it overflows. So a wide `:width`/`:height` (e.g. 1600×900 hero) is fine.
- **⚠️ Crop at full resolution — never upscale a viewport-sized crop.** Because the on-screen viewport is auto-fit small, the crop MUST be taken from the SOURCE image via Cropme's `crop({ width: <output px> })` option (it re-renders from the original pixels), then normalized to exactly `width`×`height` on a canvas and JPEG-encoded (~0.92). The old handler cropped at the viewport size and *stretched that tiny image up* to `width`×`height` → severe blur. Do not reintroduce that upscale. The **Upload** (`uploadAsIs`) path already sends the original full-resolution bytes untouched. (Regression-tested with a high-frequency source: full-res crop preserved ~331 stripe edges vs **0** for the upscale path.)

### Boundaries
- Does **not** override the LOCKED profile-picture cropper config — the profile cropper keeps its desktop modal + fixed dims.
- Desktop callers may keep modal mode (fine on a large screen); this rule is about the **mobile** experience.

> Reference implementation: `resources/views/admin/platform/mobile/activities.blade.php` (activity hero uploader). The widget lives at `resources/views/vendor/takeone/components/widget.blade.php` + `widget-crop-body.blade.php`.

---

## Mobile Pattern Language — the BASE for every mobile view — STRICT

**Rule:** Every mobile view — new or edited — is built from the named patterns below. This is the default vocabulary; do not invent a new structure when one of these fits, and never port a desktop layout to mobile unchanged. When a desktop screen is dense (many tabs, many fields, long tables), the mobile answer is **restructure**, not shrink.

> Canonical reference implementations: `resources/views/admin/club/details/mobile.blade.php` (hub + drill-down, completion ring, live preview), `resources/views/admin/club/roles/mobile.blade.php` (hub + drill-down, coverage meter, search + filter chips, live upsert into a panel list) and `resources/views/admin/club/roles/mobile-access-form.blade.php` (bottom sheet, selection cards, progressive disclosure).

### The core principle
**One screen, one job.** The reason the pattern works is not the styling — it is that each screen asks the user for one thing. The same tokens applied to 40 fields on a single scroll still feel bad. If a mobile screen is doing more than one job, split it before styling it.

### 1. Structure — Hub-and-spoke drill-down
*(a.k.a. list-detail / master-detail; iOS "hierarchical navigation")*

The replacement for desktop tabs. A **hub** lists the sections; tapping one opens a focused **detail panel** with a back affordance.

- Hub rows are a **grouped inset list**: rounded card, leading icon tile (colored per section), title, one-line summary, trailing `bi-chevron-right` (add `rtl:rotate-180`).
- Panels are **in-page `x-show` sections, not `position: fixed`** — a sticky panel header (`sticky top-0`, back · title · Save) avoids the transform/teleport trap entirely.
- Keep the submit reachable twice: in the sticky header AND as a full-width button at the end of the panel.
- Support deep links (`#section`) and, on validation failure, reopen the offending panel.
- **One form wrapping all panels** when the backend expects one submit — hidden `x-show` fields still post. Never split into per-panel partial saves unless the endpoint genuinely supports it (check for "absent key wipes data" behavior first).

### 2. Progressive disclosure
Long lists collapse into groups that are **closed by default**, each showing a live count badge so the state is readable without expanding. Add per-group and global **Select all / Clear** actions when the items are checkboxes. Use `x-show` + `x-transition` — **`x-collapse` is NOT available** (the Alpine collapse plugin is not registered in this project).

### 3. Controls
- **Selection cards** replace any dropdown with a short, known option set (≈2–7 items): full-width tappable rows acting as a radio group — icon tile, label, radio-style check circle, `border-primary bg-primary/5` when selected. Never a pop-over inside a scroll container: an `absolute` panel is clipped by any `overflow-y-auto` ancestor.
- Keep a styled Alpine dropdown (`<x-select-menu>` / `<x-*-dropdown>`) only for long or searchable vocabularies (country, currency, timezone).
- Native `<select>` / native date-time pickers remain banned (Design Rule #4).
- Tap targets ≥44px; checkboxes ≥18px.

### 4. Feedback & motivation
- **Completion meter** — a progress ring / "profile strength" scored from real signals, plus a **status dot** per hub row (green = done, amber = missing). Makes "is my setup finished?" answerable without opening anything.
- **Live preview / direct manipulation** — edit the thing, not a form describing it. Bind the hero (name, logo, cover) or a phone mockup to the inputs so changes are visible as they are typed or picked.
- **Bottom sheet** for focused sub-tasks, with a drag handle; teleported to `<body>` per the rule above.

### 5. Motion & surface
Reuse the shared motion system in `app.css` — `m-hero` (mesh-gradient band + sheen), `m-card`, `m-press`, `m-float`, `m-bar-fill`, `mobile-stagger`, `m-in*`, and **`m-panel-in`** (the drill-down panel entrance). Do not invent parallel animation classes. Respect `prefers-reduced-motion` (the shared classes already do).

### Anti-patterns — do not ship
- Desktop tabs rendered as a horizontal scroll strip on mobile.
- A read-only mobile page for a screen that is editable on desktop, plus a "edit this from desktop" note. Mobile must reach feature parity (see **Mobile / Desktop Separation** + the APK rule — mobile web *is* the Android app).
- An absolutely-positioned dropdown inside a scrolling sheet body.
- One endless scroll of every field, or a checkbox wall with no grouping.
- Native browser dialogs, native selects, off-palette one-off styling.

### Naming
When describing this work, the phrase is: **"a mobile-first settings hub with drill-down detail panels and progressive disclosure."**

---

## Skills

### `frontend-design`
Use the `frontend-design` skill whenever the user asks to build or design new UI — pages, components, modals, cards, sections, or any visual interface work. It generates production-grade, polished frontend code that avoids generic AI aesthetics.

**Trigger on:** "build a page", "create a component", "design a section", "add a new view", "make a modal/card/form", or any request that involves writing new Blade/HTML/CSS.

**Do not trigger on:** pure backend changes, bug fixes to existing UI, or minor copy/label edits.

The skill must still respect all Design Rules above — it enhances quality but does not override the rule against modifying existing UI without instruction, the Component-First / Standalone Component rules, or any Security rule.

#### Design System — the skill MUST follow these patterns exactly

**Color palette (defined in `resources/css/app.css` `@theme`):**
- Primary: `hsl(250 65% 65%)` — purple. Use `bg-primary`, `text-primary`, `border-primary`
- Background: `hsl(220 15% 97%)` — near-white gray. Use `bg-background`
- Card/surfaces: `bg-white` or `bg-card` with `rounded-xl shadow-sm`
- Muted: `bg-muted` (`hsl(220 15% 94%)`), `text-muted-foreground`
- Border: `border-border` (`hsl(210 14% 80%)`)
- Success: `text-green-600` / `bg-success`
- Destructive/danger: `bg-destructive text-white`
- Accent: `bg-accent` (`hsl(250 60% 92%)`) for subtle highlights

**Typography:**
- Font: `Inter` (loaded from Google Fonts)
- Page headings: `text-xl font-bold` or `text-3xl font-bold text-gray-900`
- Sub-headings: `text-sm font-medium text-muted-foreground`
- Body: `text-sm text-foreground`
- Labels/metadata: `text-xs text-muted-foreground`

**Layout patterns:**
- Page wrapper: `px-4 sm:px-6 lg:px-8 py-4` (member pages) or `space-y-6` inside `admin-club` layout — **full width, no `max-w-* mx-auto` cap** (matches `/admin`'s edge-to-edge sidebar content; removed from every desktop page in July 2026)
- Page header: `flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4` with a title block on the left and action buttons on the right
- Cards: `bg-white rounded-xl shadow-sm border border-gray-100 p-4` or `p-6`
- Grids: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`
- Stat rows: `grid grid-cols-2 sm:grid-cols-4 gap-4`

**Buttons:**
- Primary action: `bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium` or use `.btn.btn-primary`
- Outline/secondary: `border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors`
- Destructive: `border border-red-300 text-red-600 hover:bg-red-50` or `.btn.btn-danger`
- Icon-only (sidebar actions): `w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border`
- Pill/badge style: `px-3 py-1.5 rounded-full text-xs font-medium`

**Tabs:**
- Use `border-b border-gray-200` container with `nav -mb-px flex gap-8`
- Active tab: `border-b-2 border-purple-500 text-purple-600 font-medium text-sm`
- Inactive tab: `border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm`
- Count badges inside tabs: `ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600`

**Forms & inputs:**
- Input: `w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent`
- Search input with icon: wrap in `relative`, use `pl-10` on input, SVG icon `absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400`
- Labels: `block text-sm font-medium text-gray-700 mb-1`
- Select: same border/radius pattern as input

**Icons:**
- Use Bootstrap Icons exclusively (`bi bi-*` classes). No Heroicons, Lucide, or FontAwesome.
- Inline with text: `<i class="bi bi-{name} mr-2"></i>`
- In icon-only buttons or stat cards: `<i class="bi bi-{name} text-xl"></i>` or `text-2xl`

**Interactivity:**
- Dropdowns, modals, toggles: Alpine.js (`x-data`, `x-show`, `x-cloak`, `@click.outside`)
- Modals use the custom Bootstrap bridge in `app.blade.php` — trigger with `data-bs-toggle="modal"` or `bsModal.show(el)` / `bsModal.hide(el)`
- Toasts: call `window.showToast('success'|'error'|'info'|'warning', 'message')`
- Transitions on dropdowns: `x-transition:enter="transition ease-out duration-100"` / `x-transition:enter-start="opacity-0 scale-95"` / `x-transition:enter-end="opacity-100 scale-100"`

**Mobile responsiveness (required on every new view):**
- All headers: `flex-col sm:flex-row`
- All tables/tab groups: wrap in `overflow-x-auto`
- Modals: `max-w-[calc(100vw-2rem)]` or `w-full max-w-lg`
- Dropdowns: `max-w-[calc(100vw-2rem)]`

**What to avoid:**
- Do NOT use Bootstrap CSS classes (`.btn`, `.card`, `.modal` etc.) unless they are already present in the file being edited
- Do NOT use arbitrary Tailwind colors like `bg-blue-500` for primary actions — always use `bg-primary`
- Do NOT use inline `style=` for colors that have a Tailwind token equivalent
- Do NOT add gradients, glassmorphism, or decorative effects unless the specific context already uses them (e.g. the public club hero banner)

---

## Blade Component Library — REUSE FIRST

**Rule:** Before building any new UI element, check this catalog. If a component covers the need, use it — do not re-implement it inline. When a new reusable component is created, add it here immediately. New components must satisfy the **Standalone Self-Contained Components** contract.

All components live in `resources/views/components/` and are called as `<x-{name}>`.

### Dropdowns / Form Selects

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-birthdate-dropdown>` | Day/Month/Year cascading picker with live age badge | `name`, `id`, `value`, `label`, `minAge`, `maxAge`, `minYear`, `maxYear`, `required`, `error` |
| `<x-blood-type-dropdown>` | Blood type selector (A+, A-, B+, etc.) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-call-code-dropdown>` | Country dial-code `<select>` | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-country-code-dropdown>` | Searchable calling-code picker (Alpine) | `name`, `id`, `value`, `required`, `error` |
| `<x-country-dropdown>` | Searchable country name picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-currency-dropdown>` | Searchable currency picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-gender-dropdown>` | Gender selector | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-gender-toggle>` | Two-button Male/Female selector (Alpine-bound) — Male shades blue, Female shades pink when selected | `model` (Alpine state path, e.g. `self.gender`), `maleLabel`/`femaleLabel` (Alpine label expressions), `maleValue`/`femaleValue` |
| `<x-marital-status-dropdown>` | Marital status selector | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-relationship-dropdown>` | Family relationship type selector | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-timezone-dropdown>` | Searchable timezone picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |

### Modals

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-activity-modal>` | Create or edit a club activity with image cropper | `club`, `mode` (`create`\|`edit`) |
| `<x-club-modal>` | Create/edit club — multi-tab (basic-info, contact, location, branding, finance) | `mode` (`create`\|`edit`), `club` |
| `<x-confirm-dialog>` | Async JS confirmation dialog — include once per layout; invoke via `window.confirmAction({title, message, type, confirmText})` → returns `Promise<bool>` | _(no props)_ |
| `<x-expense-modal>` | Record an expense/transaction | `club` |
| `<x-image-upload-modal>` | Standalone crop-and-upload image modal (Alpine) | `aspectRatio`, `maxSize`, `title`, `uploadUrl` |
| `<x-income-modal>` | Record manual income | `club`, `currency` |
| `<x-member-create-modal>` | Create new member with optional guardian/family (Alpine) | _(no required props)_ |
| `<x-profile-modal>` | Full user profile edit/create modal — tabs: photo, personal, social, additional | `user`, `formAction`, `formMethod`, `mode` (`edit`\|`create`), `cancelUrl`, `showRelationshipFields`, `relationship`, `title`, `subtitle`, `submitText`, `submitIcon`, `eventName`, `showPasswordFields`, `showEmailField` |
| `<x-registration-walkin>` | Multi-step walk-in member registration | `club`, `packages`, `eventName` |
| `<x-user-picker-modal>` | Search and select a platform user (Alpine) | _(no required props)_ |

### Cards

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-member-card>` | Member summary card — age group badge, guardian, member-since | `member`, `href`, `footerLabel`, `footerStyle`, `guardian`, `memberSince`, `cardClass` |
| `<x-package-card>` | Club package card — cover image, pricing, schedule, activities, capacity | `package`, `club`, `instructorsMap`; slots: `actions`, `footer` |
| `<x-gender-avatar>` | Portrait fallback avatar — gendered head-and-shoulders silhouette on a colored tile, for users with no profile picture. Pass sizing/rounding/border via `class`. | `gender` (`m`/`f`/…), `bg` (default `hsl(250 55% 60%)`) |

### UI / Display

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-toast-notification>` | Global toast container — **include once per layout**; call `window.showToast(type, message)` from JS | `position` (default `top-right`) |
| `<x-qr-code>` | Offline QR (bacon, server-rendered SVG) — trigger button opens a modal with the code + Download PNG/SVG + Copy link + optional printable poster. Build target URLs via `App\Http\Controllers\QrController::clubPageUrl/clubRegisterUrl/memberUrl/eventUrl()`; poster routes are `qr.club.page`, `qr.club.register`, `qr.member`, `qr.event`. QR rendering helper: `App\Support\Qr::svg()`. | `url` (required), `title`, `caption`, `filename`, `label`, `icon`, `size`, `posterUrl`, `buttonClass` |
| `<x-stat-card>` | KPI stat card with sparkline, trend indicator, and live-update API (`StatCard.update(cardId, detail)`). Supports click navigation via `href`, `modal`, or `on-click`. | `label`, `value`, `sub-label`, `icon` (bi-*), `icon-bg`, `icon-color`, `size` (`sm`\|`md`\|`lg`), `spark-data`, `spark-labels`, `spark-color`, `trend`, `trend-up`, `refresh-event`, `card-id`, `href`, `modal`, `on-click` |
| `<x-admin-hero>` | Platform/club-admin page hero band (purple gradient, control-center style) — title + optional eyebrow/subtitle, optional right-side `count` chip, and an `actions` slot for buttons/badges. Used on the platform dashboard + admin list pages. | `title` (required), `eyebrow`, `subtitle`, `icon` (bi-*), `count`, `countLabel`; slot: `actions` |
| `<x-financial-chart>` | Monthly income/expense Chart.js bar chart with drill-down modal | `monthlyData`, `transactions`, `currency`, `canvasId`, `maintainAspectRatio`, `canvasHeightAttr`, `containerClass` |
| `<x-location-map>` | Leaflet map with address search and lat/lng hidden inputs | `id`, `latName`, `lngName`, `addressName`, `lat`, `lng`, `address`, `defaultLat`, `defaultLng`, `height`, `required` |
| `<x-client-paginator>` | Client-side pagination for any list filtered via JS. Renders the container div and injects the `ClientPaginator` JS class (once per page). Instantiate in JS: `new ClientPaginator({ itemsSelector, containerId, perPage, countBadgeId, scrollTargetId, labelSingular, labelPlural, filterFn })` then call `.refresh()` when filters change. Registered in `window._pagers[id]` for inline `onclick` access. | `id` (required), `perPage` (default `20`) |

> **`<x-stat-card>` sparkline alignment rule:** Always pass `:spark-data` from the same domain as the card's value (e.g. revenue card → monthly revenue array, not monthly member counts). When no real time-series exists yet, pass `array_fill(0, 12, 0)` as a flat baseline — never reuse another card's unrelated data array. The component is `flex flex-col` with `mt-auto` on the sparkline, so it always pins to the bottom of the card in equal-height grid rows. Do not remove these classes from the component.
>
> **`<x-stat-card>` constrained eager-loading:** When loading models for stat card data via constrained eager loads (e.g. `user:id,name,...`), always include `updated_at` in the column list. The `member-card` component uses `$member->updated_at->timestamp` for image cache-busting — omitting it causes a null-dereference that silently breaks the AJAX response.
>
> **`@json()` in Blade with nested arrays:** Do NOT write `@json($arr['key'] ?? [0,0,...,0])` — Blade's bracket-matcher chokes on `['key']` followed by `?? [literal array]` inside a single `@json()` call. Pre-assign to a `@php` variable first, then use `@json($variable)`.

### Form Utilities

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-select-menu>` | **Generic styled dropdown — the default replacement for any native `<select>`** (Design Rule #4). Rounded trigger (rotating chevron, purple focus ring) + `rounded-xl` fade/slide panel with `hover:bg-muted/60` rows and a `text-primary` check on the selected item; closes on click-outside/escape. Two modes: pass **`model`** (an Alpine state path, e.g. `cat`) to bind a property in the PARENT Alpine scope (use instead of `x-model`); OR omit `model` and pass **`value`** for a standalone server-form control (seeds state, posts via the hidden `name` input). Prefer a specialized `<x-*-dropdown>` when the vocabulary matches (gender/country/etc.). Do NOT use for JS-coupled selects (options read by `id`/`data-*`, built in JS strings, `form.reset()`-cleared, or dynamic `x-for` options) — those stay native. | `model`, `value`, `options` (`[['value'=>,'label'=>], …]`), `name`, `placeholder`, `error`, `change` (Alpine expr run after pick), `panelClass` |
| `<x-date-picker>` | **Calendar field — the default replacement for `<input type="date">`** (Design Rule #4). Rounded trigger (calendar icon, formatted date, rotating chevron) + a month grid that expands **inside the normal flow** — never `position: absolute` — so a scrolling bottom-sheet body can't clip it. Today is ringed, the selection is filled `bg-primary`, out-of-range days are disabled, and Today / Clear sit in the footer. Value is always an ISO `YYYY-MM-DD` string. Pass **`model`** (Alpine path in the parent scope) to bind parent state, or omit it and pass **`value`** for a standalone server-form control posted via `name`. Use `min-expr` / `max-expr` / `name-expr` when the bound range or field name is itself reactive (e.g. a form that toggles between a one-off date and a recurring day). All behaviour is inline `x-data` — no registration script, so it survives mobile-shell content swaps. | `model`, `value`, `name`, `min`, `max`, `minExpr`, `maxExpr`, `nameExpr`, `placeholder`, `error`, `change`, `dayOnly` |
| `<x-rich-text-editor>` | WYSIWYG rich-text editor (Alpine + `contenteditable`). Toolbar: bold/italic/underline/strikethrough, text color, H1/H2/H3/paragraph/quote, bullet+numbered lists, indent, align, link (inline URL bar, no native prompt)/unlink, horizontal rule, undo/redo, clear. Submits HTML via a hidden `<textarea name="{name}">`. Supports `dir="rtl"`. ⚠️ Its output is untrusted HTML — sanitize server-side before storing and never echo it unescaped without sanitization. | `name`, `id`, `value`, `dir`, `placeholder`, `minHeight` |
| `<x-image-upload>` | Inline image upload with crop preview (uses takeone-cropper) | `id`, `name`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `placeholder`, `placeholderIcon`, `buttonText`, `rounded`, `showPreview` |
| `<x-takeone-cropper>` | Raw cropper widget (used inside `image-upload`). Pass `:inline="true"` for the **mobile-first bottom-sheet** crop editor (**required on mobile** — see *One Cropper Everywhere*); default is desktop modal mode. `:uploadAsIs="true"` shows a second full-res upload button (label via `uploadAsIsText`). `:showControls="false"` swaps the zoom/rotation sliders for pinch/twist touch gestures. `sheetMaxWidth`/`sheetClass` shape the inline sheet; `saveText` relabels the crop button; `:showCancel="false"` hides the footer Cancel (header ✕ closes). Viewport auto-fits `canvasHeight`; handlers are teleport-safe (delegated). | `id`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `buttonText`, `buttonClass`, `mode` (ajax\|form), `inputName`, `canvasHeight`, `uploadAsIs`, `uploadAsIsText`, `inline`, `showControls`, `showCancel`, `saveText`, `sheetMaxWidth`, `sheetClass` |
| `<x-schedule-time-picker>` | Days-of-week multi-select + start/end time inputs | `id`, `daysName`, `startTimeName`, `endTimeName`, `selectedDays`, `startTime`, `endTime`, `required`, `showLabels` |
| `<x-social-links-editor>` | Editable list of social media links (add/remove/reorder) | `links`, `containerId` |
| `<x-social-link-row>` | Single social link row — used internally by `social-links-editor` | `index`, `link` |

---

## Coding Conventions

- PHP 8.2 — use named arguments, match expressions, null-safe operator where appropriate
- Blade components live in `resources/views/components/`
- Use `@stack('scripts')` / `@stack('modals')` to inject page-specific JS and modals
- AJAX responses return `response()->json(['success' => true/false, 'message' => '...'])`
- Authorization checks follow the pattern: super-admin → own profile → family relationship → club admin of member
- Throttle middleware is applied on all write routes (`throttle:member-write`, `throttle:admin-write`, `throttle:uploads`, etc.) — and on expensive reads/lookups per the anti-enumeration rules
- **Guard `@php` helper-function declarations.** The member profile (`components-templates/member/show.blade.php`) and the super-admin member view (`family/show.blade.php`) are near-identical twins that each declare top-level PHP helpers in `@php` blocks (`calculateTimeDifference`, `getChangeIcon`, and `calculateAgeAtDate` in their `affiliations-enhanced` partials). Any PHP function declared in a Blade `@php` block MUST be wrapped in `if (! function_exists('name')) { … }` — otherwise rendering both views (or the same view twice) in one request/process is a "Cannot redeclare" fatal. JS functions inside `<script>` are exempt.

### Member profile — self-service create flows (built on `member/show` + mirrored to `family/show`)
The member/family profile "Actions" menu creates records via shared endpoints on `MemberController` (auth: super-admin → own profile → guardian). Each opens an Alpine modal (`@open-*-add-modal.window`), POSTs JSON, and patches the DOM in place (No-Reload rule):
- **Goals** — `member.store-goal` / `family.store-goal` (`StoreGoalRequest`, `Goal` model). Goals had update-only before; this added create.
- **Attendance** — `member.store-attendance` / `family.store-attendance` (`StoreAttendanceRequest`, `Attendance` on `members_attendance`).
- **Event participation** — `member.store-event` / `family.store-event` (`StoreMemberEventRequest`, new `MemberEvent` model / `member_events` table): a free-form personal event log, distinct from club-event registrations (`ClubEventRegistration`) and tournaments (`TournamentEvent`).
- **Achievements stay club-awarded** (read-only `member_award` via `ClubAchievement`) — there is intentionally no member self-service "add achievement".
- Tests: `GoalTest`, `AttendanceRecordTest`, `MemberEventTest`, `ProfilePageRendersTest`.

### People discovery (member-to-member)
`PeopleController` provides a platform-wide **"Find People"** search (`me.people` page + `me.people.search` AJAX) and a **SAFE public profile** at `people.show` (`/people/{uuid}`). Views are device-split: `people/{mobile,desktop}/{index,show}.blade.php` (+ `people/partials/club-row`).
- **`users.is_discoverable`** (bool, default true) = opt-OUT of discovery. It gates BOTH search visibility AND cold DMs — a discoverable member has opted into being found *and* contacted, so `User::canMessage()` allows messaging any discoverable member. Toggle in `/me/settings` → `me.discoverable.update`.
- **Public profile shows only safe data** (name, photo, clubs active+history, skills, medals, challenge win-rate). It must NEVER expose health/billing/documents/contacts/family — those live only on the family/admin-gated `member.show`. `User::canViewPublicProfile()` = any signed-in viewer not blocked either way; viewing your own → redirect to `member.show`.
- **The old `/u/{slug}` wall + follow notifications now redirect to `people.show`** (was `member.show`, which 404'd for non-family). New "view another member" links should target `people.show`, not `member.show`.
- Follow routes are `wall.follow` (POST) / `wall.unfollow` (DELETE) bound by `{user:slug}` — use the slug, and DELETE to unfollow. Tests: `PeopleDiscoveryTest`.
- The `/people/{uuid}` binding is the reference for the **Unpredictable Resource Identifiers** rule: public key = uuid, and authorization still runs on every request.
