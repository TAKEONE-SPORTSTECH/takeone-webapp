# Registration and email verification

**Purpose.** How a stranger becomes a member through `/register`, and how the
email check works. Consolidated 31 Oct 2026 from `Documentation/REGISTRATION_FIX_SUMMARY.md`
and `REGISTRATION_TEST_GUIDE.md` (Feb 2026), checked against the code. The
club QR wizard (`/register/wizard/*`, `WizardRegistrationController`) has its
own memory note (*Self-registration wizard hub*).

## Main files

| What | Where |
|---|---|
| Form + submit | `app/Members/Auth/RegisteredUserController.php` (`create`, `createForClub`, `store`) |
| Routes | `app/Members/routes.php` — `register`, `register.club` (`/register/{country}/{slug}`), `verification.notice`, `verification.verify`, `verification.send`, `verification.resend.public` |
| Welcome / verify mail | `app/Members/Mail/WelcomeEmail.php` (queued) |
| Rate limits | `AppServiceProvider` — `register` 3/min per IP, `verification` 6/min per email+IP |
| Tests | `tests/Feature/Registration/MemberAndClubDoorsTest.php`, `tests/Feature/Members/RegistrationNeverGrantsSuperAdminTest.php`, `tests/Feature/EmailVerificationToggleTest.php` |

## Data flow (`POST /register`)

1. Email is trimmed + lower-cased; uniqueness ignores case (`UniqueEmail`).
2. Validation: name, email, password (confirmed, `Password::defaults()`),
   `mobile_number` + `country_code`, gender `Male|Female`, birthdate
   (`before:today`), nationality. With `duplicates.guard.registration` on, a
   phone already held by an email-less account under the same name is refused.
3. User created; `mobile` stored as `{code, number}`.
4. If the sign-up came through a club whose `require_email_verification` is
   off, the email is marked verified and no mail is sent.
5. `Auth::login($user)` — **required**: the verify-notice route is behind
   `auth`; without the login the redirect chain dead-ended (the Feb 2026 404).
6. One `WelcomeEmail` is queued (failure is logged, never blocks sign-up). No
   `Registered` event is fired (31 Oct 2026) — it used to queue a second mail.
7. Redirect to `verification.notice`, or straight in when verification was skipped.

## Rules

- **Registration never grants super-admin** (since 3 Oct 2026). The old
  "first registered user becomes super-admin" behaviour is gone. A
  super-admin is made only on the server: `php artisan admin:make-super {email}`
  (run as `www-data`) or `SuperAdminSeeder`.
- `User` implements `MustVerifyEmail`.
- The verify link (`/email/verify/{id}/{hash}`, temporary-signed, valid
  `auth.verification.expire` minutes — 1440 by default) logs the user in if
  needed and marks the session two-factor-verified, then confirms the email.
  It does so even when the email is already verified (see the backlog).
- The public resend answers the same whatever the address, so it cannot be
  used to discover accounts.
- To confirm someone by hand: `$user->markEmailAsVerified()` in tinker, run as
  `www-data`, never root. Mail is real Gmail SMTP and queued — a stuck queue is
  the usual reason no mail arrives (see the Pre-Launch Runbook in CLAUDE.md).
