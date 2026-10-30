# Club Admin Panel — UX Findings & Fix Plan

_30 Oct 2026. Five reviewers (new owner on desktop, front-desk admin, phone-only owner, Arabic-speaking owner, senior UX expert) worked from real screenshots of all 22 club-admin pages (Victory Academy Jiu-Jitsu, owner's view, EN + AR, desktop 1440px + phone 412px). Rendered read-only inside a rolled-back transaction. Key claims re-checked in code (marked ✔)._

## The diagnosis in one line
The panel is organised around **the features we built**, not **the jobs an owner does each day** — 21 sidebar items + 4 unlabelled icons, a dashboard of things to *look at* rather than things to *do*, and numbers that **disagree with each other**, so people stop trusting it.

---

## A. Wrong or broken (fix first — these break trust)

| # | Where | Problem | Evidence |
|---|---|---|---|
| A1 ✔ | Financials, Dashboard | **Unpaid enrolments are booked as income.** Income 235 / "100% margin" while 120 is still unpaid; unpaid rows show green "+BHD 40" with method "Cash". | `SubscriptionService.php:89-98` writes an income transaction at enrolment regardless of payment |
| A2 | Everywhere | **Member count has 5 answers:** 3/20 (dashboard), 20 (mobile), Total 20 / Current 3 / Not active 17 (members), 0 active (club details), Male 23 vs 20 members. | screenshots |
| A3 | Dashboard vs Financials | Mobile hero "+235 net" (all time) vs card "+0 net this month"; Analytics "Avg revenue/member 0.00". No period labels. | screenshots |
| A4 | Dashboard / Members | **Three age-group schemes** (0-12/13-17/18-25 · 5-10/11-15/16-21/22+ · Kids/Cadet/Junior/Senior/Masters). | `ClubAdminController.php:119` |
| A5 ✔ | Members › Pending requests | **Approve / Reject buttons are dead** — `approveRequest()` / `rejectRequest()` are not defined anywhere. | `members/index.blade.php:610-614` |
| A6 ✔ | Dashboard | **Invented numbers:** Technique = rating, Discipline = rating − 0.2, Focus = rating − 0.3. | `dashboard/index.blade.php:460-472` |
| A7 | Dashboard, Financials | "income, expenses `&amp;` profit" shows literally (double-escaped). | `components/financial-chart.blade.php:40-43` |
| A8 | Financials | KPI amounts cut off: "235.…", "120.…". | desktop__financials |
| A9 | Events card (mobile) | Raw Markdown "## 🥋 …" leaking. | mobile__events |
| A10 | Charts | Smoothed curves draw one month of data as a bell over 3 months; count axes show 0.2/0.4. | dashboard, analytics |
| A11 | Members (desktop) | Legacy edit modal: `openEditModal` never called; "Leave" posts to a non-existent `/leave` route. → House-cleaning register. | `members/index.blade.php` ~1611 |

## B. People get lost (navigation & wording)

| # | Problem | Fix |
|---|---|---|
| B1 | 21 equal-weight sidebar items in 5 groups; mobile has a *different* 7-group map. | Regroup by job (see plan) — same map on both. |
| B2 | **Club settings has no menu entry** — it is an unlabelled gear among 4 tiny icons (back / eye / send / gear). Owner can't change his logo. | Labelled "Settings" row; label or move the icons. |
| B3 | Top bar mixes the **member app** (Feed/Schedule/Challenge/Events/Market) above the admin sidebar; "Events" means two different things. | Admin top bar = club switcher, search, bell, "My member app". |
| B4 | Jargon: Perks, Sparring, Verifications (of what?), Timeline, Ledger, Not Active, Favicon, Slug, Schedule Type, Test Mode, Personal View, "The Pillars of Progress". | Plain labels: Classes, Memberships & prices, Payments, Posts, Medal approvals… |
| B5 | Mobile: menu opener is **your own profile photo** (27×36px); 22 destinations hidden behind it. | "More" tab in the bottom bar. |
| B6 | Mobile bottom bar = Home · Members · Finance · Packages · Shop — Packages/Shop are weekly jobs; messages, approvals, today's classes are daily. | Home · Members · Money · Inbox · More. |
| B7 | Fake buttons: decorative hero tiles styled exactly like real 48px buttons (dashboard speedometer, members people tile…). | Remove or make obviously decorative. |
| B8 | Unlabelled icon actions (chart-arrow, check-square, QR, two different bells). | Text labels under icons. |
| B9 | Three page-header styles across 22 pages; Messages has none. | One hero band everywhere (Design Rule #6). |
| B10 | Irrelevant defaults: Sparring offers Karate/TKD to a BJJ club (off-palette cyan button); Shop occupies a tab for clubs that don't sell. | Filter to the club's sport; show Shop only when used. |

## C. The dashboard doesn't answer "what needs me today?"
- 10 tiles; only **Pending payments** is actionable. Hall of Fame, activity colour strip, "1 activity", package carousel and a full-width year chart (flat for 11 months) take the fold.
- Missing: who owes, whose package ends this week, join requests, medal approvals, **today's classes**, setup gaps.
- Mobile "Pending payments" rows link to Members, which then says "approvals are on the desktop".

## D. Daily tasks that are slow, impossible or risky (front desk)

| Task | Today |
|---|---|
| Walk-in child + parent | 4 steps, ~15 fields. **Parent and child birthdate/phone/gender required** — breaks our own rule (staff need only a name). Parent never looked up → duplicate parent accounts. Everything booked as **paid**. |
| Enrol + record cash | Two separate flows (~10 clicks). **No payment method, no "paid now / later", no Reject.** |
| Approve transfer proof | Works on desktop; on mobile the proof image is **never shown** before "Mark paid". |
| Who expires this week | **Impossible** — no filter, column or sort for end date. |
| Tell parents "Friday cancelled" | Goes to the child's own account (no email) not the guardian; can't target one class; three different "announce" doors. |
| Who came today | **Impossible in admin** — attendance only feeds an analytics count. |
| Find a member | Default filter "Active (3 of 20)" hides 17 people; phone search fails with "+973"/spaces. |

## E. Mobile = the Android app
- 8 screens say "do this on the desktop" (`lang/en/admin.php:77,90,96,114,118,199,205,274`) — against our own Mobile Pattern Language.
- Tap targets under 44px (drawer ✕ 32px, package ⋮ ~32px, labels 10px); every primary action top-right (hardest thumb zone).

## F. Arabic
- Data areas are English: financial chart (title, legend, axis, months), package cards (Single Activity, Mixed, Sat/Mon), age bands, "Package: Junior" written into the DB, Cash/Subscription, cropper, map, pickers, rich-text toolbar, AI writer.
- Bidi bugs: times "PM – 7:00 PM 6:00", ages "y∞-17", dates "Sep 2026 29", money "BHD 35.00+".
- Wording: Timeline→أخبار النادي, Sparring→نزالات تدريبية, Verifications→طلبات اعتماد الإنجازات, Personal view→حسابي الشخصي; one word for income (الإيرادات).

## G. Keep — what works (all reviewers agreed)
- **Pending payments** card (names + amounts) and **Recent activity** feed.
- **Mobile Club Details hub**: drill-down rows, completion ring, status dots — the model for the whole panel.
- Members: quick-filter chips with counts, cards/table toggle, select + bulk enrol with backdate, the Add-member menu, walk-in "Total to collect" step and "waive fee".
- Financials ledger chips + search; mobile Financials hub (big Income/Expense cards, month switcher).
- Toasts + in-place refresh everywhere; `confirmAction` (app-safe); correct RTL mirroring of the shell.
- The `ContributesClubAdminNav` contract — regrouping is configuration, not a rewrite.

## H. What Martialytics teaches us (from `drafts/martialytics-study.md`)

Martialytics is easy to learn for one reason: **its menu is the owner's day, in 8 plain words** — Dashboard · Attendance · Leads · Send · Grading · Study · Calendar · Shop — with everything set-once (settings, billing setup, waivers, widgets, app) tucked under the **school-name menu** top right. Ours is 21 module names in a sidebar.

**Take (adapted to our design system):**

| Their pattern | What we build |
|---|---|
| 8 job-named top-level areas; setup lives under the club-name menu | Our map becomes jobs, not modules (see Phase 3). Set-once things go under a labelled **Settings** / club menu. |
| Dashboard widgets with a **time-window chip** (7/14/30/60/90 days): Billing alerts (overdue · expiring · contract renewals), **Birthdays**, **Absent students** (7+/14+/30+ days), **Currently attending** | Our "Needs attention" block uses the same idea: each row has a window chip and a one-tap action (Remind · Mark paid · Renew · Message). We already have the data: subscriptions end_date, `class_attendances`, birthdate. |
| Student list: filters remembered between visits, sort by **last attendance / plan status / contract expiry**, the sort value shown **under the name**, bulk bar **Export · Tags · Pay · Send** | Members list gets the same sorts, the contextual sub-line, "Ending soon" chip, bulk Message/Export, and searches every status by default. Tags/groups later (Phase 5). |
| **Attendance** screen: pick date + class → roster grouped by belt → tick names; a past date = backdating | An admin **Attendance** page over the existing `class_attendances` — today by default, any date for corrections. Kiosk/self check-in later. |
| **Grading** traffic lights (ready / almost / not yet) + bulk "Promote these students" + emailed report | We already compute this (`App\Ranks\Support\Eligibility`). Surface it: a "Ready to grade" count on the dashboard and a sort on Members. |
| **Send** = one place: Chat · Bulk email · Bulk SMS · **automated emails** (lead added, birthday, absent X days, renewal due, graded up) | Merge our Messages + Notifications into one **Messages** area: Inbox · Announcements · Automations. WhatsApp instead of paid SMS (already configured per club). |
| 5-step **onboarding wizard** + a "Get Started" button that lights up when the minimum is done | A **Setup checklist** on the dashboard (logo, currency, first package, first class, first member) until done — reuse the mobile Club Details completion ring. |
| Plain permission groups: Administration · Income & Billing · Personal details · Attendance & POS | Role **presets** in Staff access: Front desk · Coach · Finance · Full admin (our fine-grained roles stay underneath). |
| Leads CRM (source, follow-up date, convert) and One Touch Sign-Up per channel with conversion chart | Leads is a new feature, Phase 6. Our QR registration wizard already covers sign-up; add per-QR source tracking later. |
| Destructive delete requires typing **DELETE** | Use for deleting a member/package with history (via `confirmAction` with a typed confirm). |

**Avoid their mistakes:** setup summary showing 0 after data was added (same disease as our disagreeing counts — fixed by Phase 2), dated look and leftover demo text, no Gulf currencies, no Arabic/RTL, weak phone admin, staff-only CSV import, pause-a-plan-only-once.

**Where we already beat them — keep it visible:** BHD and Arabic RTL, families/guardians, phone-first admin inside the app, events/tournaments/scoreboard, expenses + profit, chains and multiple owners, no payment gateway needed.

---

## Fix plan (each phase separately approved; RULE #1 — additive, behind a flag)

**Phase 1 — Honest & working (bug fixes, no redesign).** A5 dead buttons, A6 invented bars, A7 `&amp;`, A8 truncation, A9 markdown, A10 bars instead of curves, walk-in required fields → `PersonFieldRules`, member search across all statuses + phone normalisation, Sparring filtered to the club's sport, translate the hardcoded strings and fix bidi (F).

**Phase 2 — One set of numbers.** A shared `ClubMetrics` defining member / active / collected / owed / ending soon / absent / age bands, read by dashboard, members, financials, analytics, club details; a test asserting they agree. **Income = money collected**; owed shown separately (decision needed — changes what the books show).

**Phase 3 — A menu that is the owner's day (flag `CLUB_ADMIN_V2`, default OFF).** One map for desktop and mobile, Martialytics-style job names:
- **Home** · **Members** (list, coaches, approvals) · **Attendance** · **Classes** (timetable, memberships & prices, facilities) · **Money** (payments, expenses, reports) · **Messages** (inbox, announcements, automations) · **Grading** · **Events** · **Shop** (only if used).
- Club page content (posts, gallery, achievements) and set-once things (club details, staff access, registration QR, WhatsApp) under a labelled **Settings / club menu**.
- Admin-only top bar; mobile bottom bar **Home · Members · Money · Inbox · More**; labelled icons; one hero band on every page.

**Phase 4 — A dashboard that asks for action.** Hero with Add member · Record payment · Message members → **Needs attention** with window chips (owes · ending in 7/14/30 days · join requests · absent 14+ days · birthdays · ready to grade · medal approvals), each row with its action → **Today on the mat** (today's classes, who's attending) → 4 honest KPIs → money this month → recent activity → **Setup checklist** until done. Year chart moves to Reports. Mobile parity: approve with proof on the phone; no "use desktop" notes.

**Phase 5 — Front-desk workflows.** Payment method + paid now/later at enrolment, Reject with reason; parent lookup in walk-in; Members sorts (last attendance, plan status, ends on) + bulk Message/Export; **Attendance** page (any date, backdate); announcements to guardians, by class/package, via WhatsApp; tags/groups; role presets.

**Phase 6 — New features from the study (each its own decision).** Leads list with follow-ups → convert; automated messages (absent, birthday, renewal, promoted); kiosk self check-in; study material per belt; club-level waivers.

Reviewer sources: screenshots + HTML in the session scratchpad (`ux/png-en`, `ux/png-ar`, `ux/html-*`).
