# Account recovery

What a member does when they cannot get into their account, and who else may
set their password. Code: `App\Members\Support\AdminPasswordReset`,
`MemberController::resetPassword()` / `regeneratePassword()`, the auth
controllers in `app/Members/Auth/`.

## The ways back in

| Situation | What happens |
|---|---|
| Forgot the password, knows the email | **Forgot password** (`/forgot-password`) emails a reset link. Throttled (`throttle:password-reset`). |
| Forgot the password, at the club | A **club admin** opens their profile and sets a new password (the desk). |
| Forgot which email or phone they used | The club admin finds them by name in the club's members list; their email and phone are on the profile. Then the desk resets as above. |
| A privileged account (see below) | Forgot password by email, or a **super-admin** — never a club admin. |
| Super-admin, any account | **Regenerate password** (`member.regenerate-password`) makes a strong one and emails it. |

## Who may set somebody else's password

`member.reset-password` (`POST /member/{id}/reset-password`) allows:

1. **The member themselves.**
2. **A super-admin**, for anyone.
3. **A club admin**, for a member of one of their clubs — **unless the member holds
   privileged access** (`User::holdsPrivilegedAccess()`): super-admin, owner of any
   club, chain or federation, club admin anywhere, federation staff, or an event
   official. Those are refused with a 403 and a message saying how they can reset.

Why the exception (security fix, 2026-10-31): a club admin can enroll any account
into their club without its owner's consent, so "is a member of my club" was
enough to take over any account, a super-admin's included.

The profile page asks the same question (`AdminPasswordReset::viaClub()`), so the
**Reset password** item is only shown when the server would accept it.

## It is never silent

Whenever somebody other than the member sets the password:

- an activity-log row `member.password_set_by_admin` records who (causer), whose
  (subject) and through which club (`properties.club`, null for a super-admin);
- the member is emailed `PasswordChangedByAdminEmail` ("Your password was
  changed"): who changed it and a link to set their own. It never contains the
  password. Members with no email on file get no mail.

Mail is best-effort and sent during the request: a failure is reported to the
log and does not undo the reset, because the desk has already done it.

Tests: `tests/Feature/Members/AdminPasswordResetTest.php`.

## Limitations and next steps

- **Admin enrollment still needs no consent.** It no longer leads to privileged
  accounts, but a club admin can still enroll and then reset an *ordinary* member.
  The email makes that visible; requiring consent to join a club is a separate
  decision.
- **The privileged check runs a few `exists()` queries** per reset and per profile
  view by a club admin. That is fine at today's scale; if profile views get hot,
  cache `holdsPrivilegedAccess()` per request.
- **Synchronous mail.** It is sent inline like the other account mails. When the
  queue is separated per environment (audit Phase 2), it can move to the queue.
