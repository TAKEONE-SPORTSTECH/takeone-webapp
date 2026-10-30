# Martialytics – Full Study

_Collected 29–30 Oct 2026 from the admin app (read-only, trial account) and the public help centre._

## Contents
- Part 1: Admin app study (every page, popup and menu found)
- Part 2: Help-centre notes, by section

---

# Part 1: Admin app study


Captured 29 Oct 2026 from Ghassan's trial account (school "TAKEONE", school id 9228, Bahrain).
Method: read-only. Every page and every hidden dialog was read from the page structure.
Nothing was saved, sent, deleted, paid or connected. The extension's screenshot, click and JS tools
were failing, so: **no colours, fonts or spacing captured, and nothing was clicked.**

---

## 0. Architecture observations

- admin.martialytics.com: one big single-page app with hash routes (#dashboard, #attendance, #leads,
  #send, #grading, #study, #calendar, #shop, #student_edit/<id>). All views and dialogs are in the page at once and shown or hidden.
- Separate server-rendered pages: /settings, /billing, /onetouch, /onetouch-checkout, /waivers,
  /portal, /widgets, /news ("Articles"), /shop (POS mode), /download_shop_stock_levels.php (it's PHP).
- Other domains: kiosk.martialytics.com (separate login), services.martialytics.com (widgets, leads POST),
  intercom.help/martialytics (help), Intercom chat bubble on every page.
- Integrations: Stripe Connect (OAuth), GoCardless (OAuth with name/email prefilled), Facebook, Zapier, Google Places.
- Rich text editor: Redactor-style toolbar (HTML, Format, Bold, Italic, Lists, Link, Line, Personalisation, Image).
- Delete pattern everywhere: type the word "DELETE" to confirm.
- A "Your feedback" box (:) :| :() and a "Give Feedback" link on every page.
- iPad prompt: "Tap to add Martialytics to your iPad Homescreen" (PWA).
- A mobile bottom nav with 8 icons mirrors the top nav.
- Leftover dev/demo text in hidden dialogs ("Luke Skywalker", "Jedi Skills Kids", "Glen Kleinig", "PROMTOE" typo,
  "Please make sure you've done a thing.", 2017–2021 dates) – sloppy but tells us the dialog layouts.

### Navigation
Top nav: Dashboard · Attendance · Leads · Send · Grading · Study · Calendar · Shop
School menu (top right, school name "TAKEONE"): School Settings · Member Billing · One Touch Sign-Ups ·
One Touch Checkout · Waivers · Student App · Widgets · News Management/Articles · Logout
Footer: Referral Program · Give Feedback

---

## 1. Onboarding wizard (/onboarding/?step=1..5)
Fixed bottom bar: Back | "Step X of 5" + step name | Next (Finish on the last step).
1. Creating your profile – owner name*, school name*, website (auto-detect brand: "Looking up your brand…"),
   brand colour #4a90d9 + secondary #2c3e50 (picker, hex, Reset), logo upload/remove. Notifications ("Logo uploaded").
2. Adding school locations – nickname*, street address* (Google Places with their own list:
   "MATCHING ADDRESSES · 5 RESULTS", weighted to the user's location), 7 hidden fields for the address parts and coordinates.
   Locations added as rows "Nickname - address…" + Delete. No map.
3. Styles – search a master style library, each with rank count (BJJ 30, TKD 19, Judo 16, Kali 11, Arnis 11,
   Silat 9, Iaido 9…). Pick several → rows + Delete. Notification "Style added".
4. Students – CSV import (beta): drag/drop or Browse, CSV only, 5MB, Change File → Map Your Columns
   (auto-matched) → Ready to Import stats (to import / total / inactive skipped / data issues skipped) →
   Proceed → "Importing… n / n" progress. Skip for Now.
5. Set up complete – counts (showed 0/0/0 despite data – bug or lazy update), What's Next cards:
   Dashboard / Member Billing / School Settings. Finish.
After the wizard: a tour overlay ("Congrats! You've set up your school successfully" → "Next, add your first
students" (10 students suggested; "contact us" to import within 24 hrs) → "Learn about and grow your school!" → LET'S GO; SKIP TOUR).

---

## 2. Dashboard (#dashboard)
- Left: Student list panel – search, FILTER STUDENTS (Active / Inactive (n) / Pending, Age between x–y,
  Tags, Venues, Styles, Clubs, Status Student/Instructor, Communication SMS/Email/Chat 3-state sliders),
  CLEAR CACHE, RESET, APPLY FILTERS. SORT BY: first name, surname, age, last attendance, style & rank, plan status,
  passes remaining, joined on, contract expiry. "0 Students", ADD NEW STUDENT.
- Bulk action bar on selection: EXPORT · TAGS · PAY · SEND · EDIT.
- Widgets/cards:
  - Billing alerts: dropdown Recurring Plans / Passes / Pending Invoices / Contract Renewals, ± 7/14/30/60/90 days.
    Empty state: "Great! You don't have any Overdue Payments or Recurring Plans expiring!"
  - Birthdays: next 7/14/30/60/90 days, previous 7/14/30.
  - Absent Students: 7+/14+/30+/60+/90+ days.
  - Currently Attending (per venue tab).
  - Live Reports (venue, Daily/Weekly/Monthly, date range + presets Last 30 days/Last month/3m/6m/year/All time):
    Attendance · Income · Growth · Styles, Ranks & Skills (avg pass rate) · Leads & Conversions (%) ·
    Demographics (gender F/M/Other, age bands <6, 7-12, 13-17, 18-24, 25-34, 35-44, 45-54, 55+, VIEW MAP). Each has VIEW DETAIL.
- Detail reports (dialogs, each with Download .CSV):
  - Attendance Report: style/venue/date, daily/weekly/monthly, Styles chart, Weekly Averages (SUN–SAT), Hourly Averages,
    Attendance History table (time, student, style, venue) + filter. Leaderboard (name, group toggle, style, rank, totals).
  - Income Report: group by Plans & Passes / Venues / Methods / Payment Types / Shop Categories; compare
    last 30 days vs 1 month ago, 3 months ago, 12 months ago. Transaction History (date, student, desc, type, method, amount) + refund ("REFUND THIS TRANSACTION?").
  - Style & Ranks Breakdown: "Students participating in [style] AND [style]".
  - Styles, Ranks & Skills: skill / style / performance. Student Grades (name, rank, grade, date).
  - Leads & Conversions Breakdown. Growth Breakdown: total active, churn, new leads, new students.
  - Demographics: filters venue/style/age/gender/club → EXPORT / SEND.

## 3. Student record (#student_edit/<id>)
Add/Edit form: photo upload, "EMAIL STUDENT FORM" (send the student a self-fill form), first name, surname, gender (M/F/Other),
address (+ photo/document upload), suburb, postcode, email, secondary email, phone, mobile (intl-tel-input, 244 countries,
default Bahrain +973, placeholder 3600 1234), Receive: 3 opt-in checkboxes (SMS/Email/Chat).
Emergency contact: name, relationship, phone, medical/allergies.
Membership: Attends (venues), Contracted Until, Member Number, Belt Size, Uniform Size.
Status: Student/Instructor, Active toggle, Club associations, Styles & Ranks (one rank select per style, each rank has colour hex),
Date Joined, Birthday. DELETE / SAVE STUDENT.
Profile view panels: Styles & Ranks (= ELIGIBLE marker), Billing (ADD A PLAN OR PASSES), Contracted Until,
Notes (auto-save "LAST UPDATED"), lead referral info (referred on / by, converted on), VIEW NOTES,
Attendance (per week; history with style/venue/date filters, CSV), Transactions (avg revenue / month,
lifetime value; date/amount/description), Waivers (sign on device with name + signature pad, Download PDF, or send request),
Contact Notes (date/method/notes), Medical Notes, Tags (add new with colour or pick existing), "Request Student Information" by email.

## 4. Attendance (#attendance)
Pick date, time, window (±30m/1h/2h/4h, Morning, Afternoon, Whole day), style, location → UPDATE.
"Checking students in for <style> at <time> in <venue>". Students grouped by rank columns ("0 STUDENTS · WHITE BELT (10TH GUP)" …).
IGNORE LIMITS & RESTRICTIONS toggle, Student Search, KIOSK MODE link (kiosk.martialytics.com).

## 5. Leads (#leads)
Show me: Today's / This Week's / This Month's / All Tasks. EXPORT CSV, search. Upcoming tasks list.
Lead card: Edit, Convert to Student (confirm), SET REMINDER TO FOLLOW UP (Tomorrow / Next Week / Next Month / Custom date / Never),
CONTACT NOTES with method (3 icons, e.g. phone call / email / SMS), date, notes.
Add lead form: referred via (Bookings Widget, Facebook, Twitter, Google+, LinkedIn, Family, Friend, Magazine, Newspaper,
TV, Brochure, Leaflet, Poster, Phone Call, Website, Word of Mouth, Walk-In, Other Advertising) + date, name, gender,
address, suburb, postcode, email, phone, birthday, associated venues, SIGN A NEW WAIVER, SAVE / DELETE LEAD.

## 6. Send (#send)
Tabs: Chat · Bulk Email · Bulk SMS.
- Recent Manual Sends table: subject, sent on, recipients, opened, clicked. Delivery reports (email: name/status/opens/clicks + DUPLICATE; SMS: name/number/status).
- Automated Emails (sequences): name; trigger "When a" Lead is added / Lead is converted / Student has a Birthday /
  Student contract renewal is due / Student is added / Student is absent / Student has graded up; ADD A STEP (each step = email with subject + rich editor incl. Personalisation). Delete needs "DELETE".
- Chat inbox: Open / Snoozed / Closed, search, filter by user, snooze (tomorrow/next week/next month), REPLY vs internal NOTE,
  side panel with student's styles & ranks + VIEW PROFILE, start conversation with student.
- Bulk Email: LOAD DRAFT, recipients from student list or SEND TO ALL LEADS / ALL STUDENTS, subject, attachment,
  drag-and-drop "Design editor", SAVE EMAIL, Schedule Send (date + time picker).
- Bulk SMS: credits remaining (+ upgrade), message title (for reporting), content 0/1600 chars with SMS-count estimate, preview,
  "Mobile Number Format Assistant" (+[country code][number] fix-up before sending), sending progress popup.

## 7. Grading (#grading)
Filter date, style, rank (multi-select with DESELECT ALL), location → UPDATE; student search; EXPORT ALL STUDENTS CSV.
List columns: eligibility (READY / ALMOST READY / NOT READY TO GRADE YET), last graded, PROMOTE. Warning if eligibility rules not set (link to settings).
Bulk: SELECT ALL, EMAIL SELECTED, "Promote these students" → EMAIL ME THE REPORT / PROMOTE STUDENTS → report emailed with belt sizes and new ranks + DOWNLOAD REPORT.
Student grading card: current rank, style, classes progress (40/50 CLASSES), READY TO GRADE, INVITE TO EVENT, PROMOTE,
SEND NOTES; skill sheet with date, skill, score (Pass/Fail), notes.

## 8. Study (#study)
Curriculum library per style: search, ADD NEW CONTENT (title, upload PDF/image, or link e.g. YouTube), upload progress %.
Shown to students in the Student App "Study" tab.

## 9. Calendar (#calendar)
Month view, venue filter, event search. CREATE NEW: Recurring class / One-off event.
Class form: name, days of week (multi), venue (or off-site one-off venue with full address), style, description,
start/end time (5-min list + typed "630p" parser + hour/minute picker), start/end date, capacity,
rank(s) allowed (multi, ALL), waiver (optional), price (optional; needs Stripe), publish options
(Widget & Portal / Widget only / Portal only / Invite only), email me when booked. SAVE CLASS.
Delete recurring: this class only / this and future / all, notify students + cancellation reason.
Bookings table: name, rank, email, billing, status, matched → REMOVE BOOKING / MATCH / UN-MATCH / CREATE STUDENT.
"Book eligible students into <event>" bulk booking.

## 10. Shop (#shop) and POS (/shop)
Admin: product search, POS MODE, STOCK LEVELS (CSV download), categories (title + colour for POS tile, preview),
product: image, name, options/variants (name, SKU, price, qty), tax-exempt flag. Delete needs "delete".
POS screen: category tabs, current order, ASSIGN TO STUDENT (name or member #), APPLY DISCOUNT (%), subtotal, PAY →
method (cash, cheque, offline card, direct debit, standing order, or Stripe saved/new card, GoCardless), email receipt, CONFIRM & PAY.

## 11. Payments inside the app (dialogs)
- One-off payment: amount, method, description, email receipt.
- Assign plan to student: recurring plan or passes plan, start date, + one-off payment, + discount, payment method
  (offline methods / Stripe saved card / new card / GoCardless direct debit (send setup form) / Send Invoice (Stripe)),
  "Automatic Transactions" (auto-record third-party payments), email receipts. "To protect your students, only certain fields are editable".
- Edit plan: remove plan, log a payment, pause/resume (once only), number of cycles, "runs until…", next payment, resume date picker, cancel subscription.

---

## 12. School Settings (/settings) – left tab list
- General: school name, primary/secondary colour (hex + picker), logo, timezone (searchable, "+3 GMT - Bahrain").
- Users & Permissions: add user (name, email, password, confirm) + per-module permissions:
  Administration (Widgets, Waivers, Shop, Send, Grading) · Income & Billing (Income, Overdues, Reports, Billing) ·
  Personal details (Address, Birthday, Notes, Email, Phone, Editing) · Attendance & POS (Attendance, Kiosk Mode, Shop POS). Resend confirmation, delete.
- Locations: address (autocomplete), suburb, postcode, state, country, nickname, phone, capacity.
- Styles & Ranks: ADD NEW STYLE (name + "Suggested style templates – fully customisable"), drag ranks to reorder,
  click a rank to edit. Rank: name, base colour, secondary colour, stripes (count + colour), grading requirements
  ("Must attend X classes AND/OR for Y days"), skills (from a skill library). MANAGE SKILLS (add/delete). Unsaved changes warning.
- Status, Clubs & Tags: custom statuses (Instructor, Student + counts), clubs (e.g. Weapons, Kata), tags with colour.
- Attendance & Kiosk: ignore plan limits in admin check-in, hide rank progress in kiosk/app, kiosk PIN, kiosk lockout minutes.
- Account & Billing: Martialytics subscription payment method (Stripe card), transactions, cancel flow with feedback.
- Notifications: student check-in (push), payment failed (email/push), chat message (push).
- Integrations: Stripe, GoCardless, Facebook, Zapier.

## 13. Member Billing (/billing)
1. Connect payment provider: Stripe / GoCardless (status NOT CONNECTED).
2. Currency and tax: AUD, CAD, EUR, GBP, NZD, SEK, SGD, USD (**no BHD/AED/SAR – a gap for the GCC**); tax name, %, tax number;
   tax-inclusive formula Tax = (Rate × Price)/(1 + Rate).
3. Plans: Recurring (name, price, every N weeks/months/years – price/interval not editable after creation) or
   Passes (name, price, number of passes, expire in N days/weeks/months). Optional restrictions: styles, attendance frequency per week/month/year.
   Plan members list (student, method, status), CHANGE PLAN (immediately with credit for unused time, or at next billing cycle).

## 14. One Touch Sign-Ups (/onetouch) – "OTSU", their headline onboarding feature
Self-signup forms shared by link/QR: form name, headline (350), intro (2500), styles, locations, notify me.
Report by date range: date, name, phone, email, billing. (Marketing site: students do details, waiver, photo and payment setup themselves.)

## 15. One Touch Checkout (/onetouch-checkout)
Paid checkout pages (needs Stripe): name, header image, rich copy, select event, select products, notify me.
Orders: date, name, email, order → view profile, email, create student, match to student, download waiver.

## 16. Waivers (/waivers)
Create waiver: name + rich text with Personalisation fields. Unsigned member list per waiver (name, age).
Deleting removes all signatures.

## 17. Student App (/portal)
Invite / revoke students (name, email, last logged in, status). App settings: logo, background colour, live phone preview.
App tabs: Home · Study · Book · Message · Profile. Home: "For you", next class card with Check in, upcoming bookings.

## 18. Widgets (/widgets)
- Bookings widget: JS embed (school id + hash, light/dark theme) or iframe; options time (morning/afternoon/evening),
  venue, style, hideFilters. "Send instructions to my webmaster" email.
- Leads widget: plain HTML form POSTing to services.martialytics.com/widgets/leads (full name, email, mobile, interests,
  referral source, honeypot field ma_website, local time offset), styled/unstyled; extra fields go to notes;
  settings: email-to address, redirect URL.

## 19. Articles (/news)
News for the Student App: title, author, publish start/end, visible toggle, hero image, rich text, views count, sort.

## 20. Kiosk (kiosk.martialytics.com)
Separate login (email + password), then a PIN-locked check-in tablet. Not opened (needs credentials).

## 21. Commercial (www.martialytics.com)
Pricing (USD/month): 0–50 students $69 · 51–100 $99 · 101–200 $129 · 200+ $199. All features on every plan, unlimited
locations/users/emails, free student app, leads and inactive students free, no revenue %, standard Stripe rates.
30-day free trial, no card. Claims 1,295+ schools. Pitch: "Chop your dojo's admin time in half", OTSU QR onboarding,
automatic failed-payment retries, branded student app.

---

## Gaps not yet captured
- Visual design (colours, typography, spacing, icons) – needs screenshots.
- CSV column-mapping screen fields (needs a test CSV upload).
- Live behaviour of: rank editor drag-and-drop, student self-signup form (public side), bookings widget (public side), student app, kiosk check-in.
- Anything that only renders after real data exists (charts, student cards, chat threads).

## Gaps/weaknesses spotted (opportunities for TakeOne)
- No GCC currencies (BHD/SAR/AED/KWD/QAR/OMR); payments limited to Stripe/GoCardless; no Arabic or right-to-left layout.
- Dated look (Redactor editor, Google+ referral option, PHP endpoints), demo text left in production dialogs.
- Onboarding summary showed 0 counts after adding data; CSV import is still beta.
- Pause/resume a plan only once.

---

## 22. Help centre pass (30 Oct) – full detail in Part 2 (~66 articles)
Key additions not visible in the admin app:
- Imports: no self-serve CSV mapping documented; their staff do imports on request ("contact us").
- Rank editor: colour auto-picked from rank name (overridable); stripes are separate ranks; first rank auto-assigned on joining a style; styles may have zero ranks.
- Traffic lights: red/orange/green from "X classes AND/OR Y days in rank"; "Class Offset" on profile backdates rank start and adds classes.
- Student Form: invites create Pending students (blue → green "Needs Approval"); admin approves or merges into the existing profile.
- Family logins: one email+password across several profiles; portal asks which profile.
- Portal members.martialytics.com and one white-label app "Martialytics Students" for all schools, branded at runtime.
- Study: content by style → rank; students see their rank and below; access per student; drag-to-order tiles.
- Bookings: statuses Booked In / Invited / Declined; widget booking matches a student only on exact first+last+email, else creates a Lead; recurring classes charge only via widget, one-off events via widget and portal; cancel link in the confirmation email.
- Kiosk: settings are per device behind a PIN; active students only; "Show Grading Details" toggle. Capacity tracker per venue (traffic lights).
- Billing: Stripe invoice flow (Pending until the student fills the form, expires at start date); existing Stripe subscriptions not importable;
  GoCardless mandate sync (auto-match + manual, re-enter password), statuses green/amber/red, manual retry, refunds outside the app.
- SMS: 160-char segments, opt-out "UNSUB https://otsu.io/out" appended, prepaid credits, sender "MARTIALARTS" or a pooled number, one-way.
- Email images by URL only. Leads export via the Send recipient picker. Converting a lead keeps card and waivers.
- Inactive = soft archive, not billed. Surname typed in Add Student offers to copy family address/contacts.
- GDPR: permanent delete, opt-in flags, data export.
Video-only articles (no text): Attendance, Kiosk, Grading, Waivers, Shop/POS, Send, Study, Portal, Leads CRM.

---

# Part 2: Help-centre notes


---

## Martialytics Help: Dashboard (collection 2856464)
Source: https://intercom.help/martialytics/en/collections/2856464-dashboard (5 articles)

---
### 1. How To Video: Navigate your Student List
URL: https://intercom.help/martialytics/en/articles/5567692-how-to-video-navigate-your-student-list
- Embedded YouTube video only (tags: Student List, Tags, filtering, sorting, custom bulk email lists). See article 2.

---
### 2. How To: Navigate your Student List
URL: https://intercom.help/martialytics/en/articles/5567696-how-to-navigate-your-student-list

#### Placement
- Student List is a panel on the **left side of the Dashboard** (persistent sidebar list).

#### Filters (filter panel opened at top of list; "Apply Filters" and "Reset" buttons)
- **Status toggles** (independent on/off toggles, can combine): **Active**, **Inactive**, **Pending** students. Default = Active only.
- Further filters: **Age**, **Tags**, **Venues** (locations), **Styles**, **Clubs**, **Billing**, **Waivers**.

#### Sort options
First Name, Surname, Age, Last Attendance, Style & Rank, Plan Status, Passes Remaining, Joined On, Contract Expiry.
- **Contextual label** under each student's name shows the value of the current sort field (e.g. style & rank, last attendance date).

#### Selection & bulk actions
- Header **select-all checkbox**; per-row checkbox (or click the student's picture) to select.
- Footer buttons: **Export** (download arrow icon → CSV of the selected/filtered students) and **Email** (send bulk email to selection).
- **Tags**: free-form custom tags added on a student's profile; usable as list filter.
- **View toggle** icon at top: **Classic** vs **Enhanced** list view.

#### Behaviour
- **Filter state is persisted** (list remembers last filter). Troubleshooting: if list shows 0 students, hit **Reset** → back to Active Students.

---
### 3. How To Video: Navigate your Live Reports
URL: https://intercom.help/martialytics/en/articles/5567755-how-to-video-navigate-your-live-reports
- Embedded YouTube video only. Tags: Dashboard, Dashboard reports, live reporting, school data. (Indicates the Dashboard contains live report widgets — e.g. Attendance Report, Absent Students, Contract Renewals, Upcoming Birthdays are referenced elsewhere.)

---
### 4. How To: Use the Absent Students Report
URL: https://intercom.help/martialytics/en/articles/5460266-how-to-use-the-absent-students-report
- Dashboard report listing **absent students** (time since last attendance) to spot drop-offs.
- Controls: **timeframe filter** (absent for period X), **sort by date ascending/descending**.
- Student name links to **profile** (update **Notes**, or email via the profile's **Send** button).
- Related automation: **automated email** triggered when a student has been absent for **X days** (configured in automations / Send).

---
### 5. How To: Export your Attendance Report
URL: https://intercom.help/martialytics/en/articles/5075510-how-to-export-your-attendance-report
- Dashboard → **Attendance Report** widget/page.
- Filters: **Style**, **Venue**, **Date range**.
- **Download arrow icon** → **CSV export** of attendance data for that filter (columns not documented; presumably student, date, class/style, venue).


---

## Martialytics Help: Getting Started (collection 194545)
Source: https://intercom.help/martialytics/en/collections/194545-getting-started (6 articles)

---
### 1. How To Video: Set up your school
URL: https://intercom.help/martialytics/en/articles/1782187-how-to-video-set-up-your-school
- Embedded YouTube video only; links to the text version (article 2). No extra text content.

---
### 2. How To: Set up your school
URL: https://intercom.help/martialytics/en/articles/3871121-how-to-set-up-your-school

#### Onboarding entry point
- Sign-up starts a **free 30-day trial**. After sign-up the user lands directly on the **School Settings** page (acts as onboarding wizard).
- A **"Get Started"** button sits top-left; it **flashes** once the minimum required fields are complete (school name, account info, at least one location, at least one style). Clicking it goes to the **Dashboard**.

#### School Settings sections (in order)
1. **School Name** – shown in the top-right corner of the app header on every screen.
2. **Account Information** – owner name, login email, password. Button: "Save Your Account".
3. **Locations** – unlimited number of locations (venues).
   - Field **Location Nickname** (essential): the short label used everywhere (reports, student profiles, filters). Recommended: town/suburb name.
   - (Other address fields implied but not detailed.)
4. **Styles and Ranks** – defines class structure.
   - **Style** = a class/programme (e.g. "Adult Kung Fu"). Name field.
   - **Ranks** = ordered list of levels within a style (belts).
     - Each rank row: rank name, **colour tile** (auto-assigned colour derived from the rank name, e.g. "White"→white; click tile to open colour picker and override), **description** field (used for sub-ranks e.g. "White Belt 1 Stripe").
     - Stripes / half-belts are modelled as separate ranks; can reuse base colour or a slightly different shade.
     - **Drag-and-drop** reordering of ranks.
     - The **1st rank** = starting rank auto-assigned to new students in that style.
     - **Grading requirements per rank** (to be eligible for the next rank): number of **classes attended** and number of **days** (time in rank), combined by an **AND / OR** dropdown. Example: White Belt needs 5 classes AND 30 days before eligible for "White Belt 1 Stripe".
   - **Grading "traffic light"** status computed from requirements: **red** = not ready, **orange** = almost ready, **green** = ready to grade.
   - Button **"Save Styles and Ranks"**; repeat per style.
   - Ranks are optional: a style with no ranks (e.g. "Personal Training") just has participants.
5. **User Accounts** (further down; see article 3).
- School Settings is reached later via the **drop-down menu in the top-right corner** of the Dashboard.

---
### 3. How To: Set up Additional Users
URL: https://intercom.help/martialytics/en/articles/3339583-how-to-set-up-additional-users

#### Multi-user logins with permission groups (4 groups)
| Permission group | Grants |
|---|---|
| **Administration** | manage Leads, Send (email/SMS), Widgets, Waivers, Shop inventory |
| **Income & Billing** | view Income Reports, Recurring Plans, Passes, Pending Invoices, Contract Renewals; manage Member Billing |
| **Personal Details** | view students' personal info, Notes section of profiles, Upcoming Birthdays widget on Dashboard |
| **Attendance & POS** | check-in via Attendance and Kiosk Mode; use Shop POS to process sales |

#### Flow
1. School Settings → scroll to **User Accounts** section → **Add New User**.
2. Fields: **Account Name** (person's name or a shared label like "Instructors" – shared group logins are allowed), email, (password implied), permission checkboxes.
3. **Default: all four permission groups ticked**; owner un-ticks what to withhold.
4. **Save User**.
5. New user receives a **confirmation email**; must verify email before logging in. Then sees only permitted areas (UI hides/blocks the rest).
- Users can be edited (permissions changed) or deleted at any time.
- Implication: owner account = full access, not restricted.

---
### 4. How To: Edit your login details
URL: https://intercom.help/martialytics/en/articles/3871104-how-to-edit-your-login-details
- Top-right drop-down → **School Settings** → **Account information** section: email + password fields → **Save Your Account**.
- **Forgot password** link on login page → reset flow by email.

---
### 5. How To: Set up Currency and Tax
URL: https://intercom.help/martialytics/en/articles/5429723-how-to-set-up-currency-and-tax
- Location: **Member Billing** page → **"Currency And Tax Set Up"** button/modal.
- Fields: **Currency** (select billing currency for students), **Tax type** (e.g. VAT/GST label), **Tax Number** (registration number).
- Behaviour: prices are **tax-inclusive**. Tax settings only make tax appear as a **line item on receipts** emailed to students (tax is back-calculated from the inclusive price). Plans/passes must be priced including tax (e.g. £30 + 20% VAT → enter £36).

---
### 6. Pricing and Plans
URL: https://intercom.help/martialytics/en/articles/2327413-pricing-and-plans
- SaaS pricing tiered by number of **Active Students**. **Inactive Students and Leads are free / not counted.** (Pricing page: martialytics.com/pricing.html.)
- Rebuild note: need an active-student counter for subscription tiering.


---

## Martialytics Help: Students & Members (collection 194554)
Source: https://intercom.help/martialytics/en/collections/194554-students-members (13 articles)

NOTE: There is **no CSV student-import article** in this collection. The only mention of import: "existing student list you would like imported? Contact support@martialytics.com to book a data import" — i.e. imports are a **staff-assisted service**, not a self-serve CSV mapper. No CSV column definitions are published here. Exports are CSV from the Student List.

---
### 1. How To Video: Navigate your Student List
URL: https://intercom.help/martialytics/en/articles/5560177-how-to-video-navigate-your-student-list
- YouTube video only (tags: Student List, Tags, filtering, sorting list, custom bulk email lists).

---
### 2. How To: Use One Touch Sign-Ups (OTSU)
URL: https://intercom.help/martialytics/en/articles/6573388-how-to-use-one-touch-sign-ups-otsu
Single public onboarding form combining: **contact info, style selection, location, billing (plan purchase), waiver signing**.
- **Branding**: uses the school's existing **Student App branding** settings (logo, colours).
- **Setup**: School Settings menu → **One Touch Signup** section → **create new form**. Fields: **Name**, **Description**, plus option toggles for what the form includes (which styles, locations, plans, passes, one-off fee, waivers).
- On save, system auto-generates a **QR code** and a **direct secure link** (unique per form). Use for posters/in-school/online; prospects complete on their own device.
- **Billing options**: choose one or more **recurring plans** to offer (student picks one), **Pass plans** (class-pack passes), and an optional **One Off fee** (e.g. joining fee). Payment taken at sign-up.
- **Waivers**: tick multiple waivers to include; all signed in the form.
- **Reporting**: per-form conversion tracking (bar chart per form so channels can be compared, e.g. Facebook link vs walk-in QR). **Click a bar → Download button** → export list of sign-ups for that form.
- Screenshot alt text: only "Mmmmmm QR Codes" (QR code image).

---
### 3. How To: Navigate your Student List
URL: https://intercom.help/martialytics/en/articles/3942178-how-to-navigate-your-student-list
(Same content as Dashboard collection version.)
- Left side of Dashboard. Filters: status toggles **Active / Inactive / Pending**; sub-filters **Age, Tags, Venues, Styles, Clubs, Billing, Waivers**; buttons Apply Filters / Reset.
- Sort: First Name, Surname, Age, Last Attendance, Style & Rank, Plan Status, Passes Remaining, Joined On, Contract Expiry; label under name shows sort value.
- Select-all checkbox + per-student checkboxes; footer **Export (CSV)** and **Email** buttons.
- Custom **Tags** added on student profile.
- **Classic / Enhanced** view toggle icon.
- Filter persists between visits; Reset restores Active list.

---
### 4. How To Video: Navigate the Student Profile
URL: https://intercom.help/martialytics/en/articles/5567889-how-to-video-navigate-the-student-profile
- YouTube video only (tags: Student Profile, student data).
- Profile features referenced across other articles: **Edit** button top-right; **Active/Inactive toggle** (green when Active) at top; sections **Contract Details** (Contracted Until), address, contact details, emergency contact, birthday, style & rank, **Notes**, **Tags**, **Send** button (email the student), profile picture, **Delete** at bottom of edit mode, **Send Student Form** in edit mode.

---
### 5. How To: Send the Student Form
URL: https://intercom.help/martialytics/en/articles/5075617-how-to-send-the-student-form
Digital self-service form for new students to enter details or existing students to update theirs.

#### New students
1. **Add New Student** → blank profile opens.
2. Top-right **Send Student Form** → enter **one email or paste a list of emails** (bulk invite).
3. Each invitee appears in **Pending Students** list (Student List filter toggle "Pending").
   - **Blue highlight** = form not yet completed (shown by email address).
   - **Green highlight + "Needs Approval" label** = completed (shown under their name).
4. Admin opens flagged profile, reviews data, adds **contract details, status, style & rank** → **Save As New Student** → becomes Active.
5. **Delete Request** at bottom of pending profile; if invitee later opens link → **error message**; need a new form.

#### Existing students (update details)
1. Student profile → **Edit** → **Send Student Form**.
2. A **duplicate pending profile** is created in Pending list; flagged for approval when completed.
3. Admin reviews → **Save Student** → merges new details into the existing profile.

- Data import: contact support to book (no self-serve import documented).

---
### 6. How To: Clone student profiles
URL: https://intercom.help/martialytics/en/articles/5075217-how-to-clone-student-profiles
- Family sign-up helper: in **Add New Student**, typing the **surname** shows an **autocomplete** of existing students; selecting one **copies address, contact details and emergency contact details** into the new profile.
- Admin then fills only: contract details (if any), style & rank, birthday (and first name).

---
### 7. How To: Use the Contracted Until feature
URL: https://intercom.help/martialytics/en/articles/5074757-how-to-use-the-contracted-until-feature
- Profile → **Edit** → **Contract Details** section → **Contracted Until** date field. Shown on profile after save.
- Used for contract renewals or **association fees** tracking.
- **Dashboard widget**: upcoming and **overdue** contract renewals; can **update renewal date directly from the Dashboard**.
- **Automated email** relative to contract renewal date: **before, on, or after** the date.
- Also a Student List sort option ("Contract Expiry").

---
### 8. How To: Set a student to Inactive
URL: https://intercom.help/martialytics/en/articles/3993524-how-to-set-a-student-to-inactive
- Profile top: **green toggle "Active"** → click to switch to **Inactive**. (Soft archive; all records retained; can reactivate.)
- **Inactive students do not count toward subscription billing.**

---
### 9. How To: Delete a Student Profile
URL: https://intercom.help/martialytics/en/articles/3845732-how-to-delete-a-student-profile
1. Profile → **Edit** (top-right).
2. Scroll to bottom → **Delete**.
3. Confirmation modal: **type "DELETE"** in a text box → **OK**.
- Permanent hard delete of all records. Inactive is the recommended alternative.

---
### 10. How To: Cancel a Student Form request
URL: https://intercom.help/martialytics/en/articles/5374111-how-to-cancel-a-student-form-request
1. Student List → open filters → toggle **off Active**, toggle **on Pending** → **Apply Filters**.
2. Click the **email** of the pending request (completed ones show under the name, green).
3. Scroll to bottom → **Delete Request**.
- Removes from Pending list; original email link then returns an **error**.

---
### 11. How To: Export a list of Active Students
URL: https://intercom.help/martialytics/en/articles/4222112-how-to-export-a-list-of-active-students
1. Student List filters → **Reset**.
2. Ensure **Active** toggled on → **Apply Filters**.
3. Footer **Export** button (down-arrow) → **CSV** of all active student data. (Columns not documented.)

---
### 12. How To: Email Inactive Students
URL: https://intercom.help/martialytics/en/articles/5374261-how-to-email-inactive-students
1. Filters: Active off, **Inactive on** → Apply Filters.
2. Select all (header checkbox) or individual students (click picture / tick box) → bulk email.
- Combine with other filters for targeting, e.g. Inactive + Venue "Winterfell" + Style "Adults Karate" → win-back promo email.

---
### 13. How To: Export a list of Inactive Students
URL: https://intercom.help/martialytics/en/articles/4222042-how-to-export-a-list-of-inactive-students
1. Filters → Reset.
2. Active off, **Inactive on** → Apply Filters.
3. Footer Export (down-arrow) → CSV of inactive students.


---

## Martialytics Help Centre: Attendance collection

Collection URL: https://intercom.help/martialytics/en/collections/194581-attendance
Collection articles (4): two are video-only, two have text. Kiosk detail has been pulled in from related articles found by searching the help centre (marked "related").

Note on screenshots: images in these articles are linked PNG/GIF files with no alt text or captions, so their content can't be described from the page data.

---

### 1. How To Video: Use Attendance
URL: https://intercom.help/martialytics/en/articles/5567803-how-to-video-use-attendance
- Embedded YouTube video only. No body text.
- Article tags/description: "Attendance, manage attendance, student attendance."
- Functional detail can't be extracted from the text (it is all in the video).

### 2. How To Video: Use Kiosk Mode
URL: https://intercom.help/martialytics/en/articles/5567772-how-to-video-use-kiosk-mode
- Embedded YouTube video only. No body text.
- Description: "Kiosk Mode, Attendance, checking in."
- Kiosk facts gathered from other articles are listed in the "Kiosk Mode consolidated" section below.

### 3. How To: Add backdated Attendance records
URL: https://intercom.help/martialytics/en/articles/3993060-how-to-add-backdated-attendance-records
Purpose: record historical attendance, or add a past check-in that was missed.

Attendance screen (top navigation item "Attendance"):
- Filter/selector controls: **Date**, **Time**, **Style**, **Location**, plus an **Update** button that loads the class roster for that combination.
- The roster lists students with a **tick/checkbox** next to each name. Ticking a name records attendance for that date, time, style and location.
- Any past date can be selected, which is how backdating works. There is no separate backdate form.
- Repeat the flow as many times as needed for bulk historical entry (the article describes one class at a time, with no bulk import).

Where a recorded attendance shows up:
- **Attendance Report** on the Dashboard.
- **Attendance History** on the student's profile.
- It also counts toward grading eligibility (class counts). See the Class Offset feature in the grading notes.
- Cross-reference: if you only need to adjust class totals without recording individual classes, use the **Class Offset** feature (B_grading-belt-management.md).

Implied data model: an attendance record = (student, date, class time, style, location). The roster is presumably the students assigned to that style/location.

### 4. COVID-19 Attendance Capacity Tracking
URL: https://intercom.help/martialytics/en/articles/4030735-covid-19-attendance-capacity-tracking
Feature: **Capacity Tracker**, a live occupancy indicator per venue.
- Shown as a **Dashboard panel**. You can view **all venues at once or each venue individually** (venue selector).
- Uses the **Traffic Light colour system** (green/orange/red) to show how close the current headcount is to the venue's maximum capacity.
- Clicking the Dashboard panel opens the **Attendance tab**, which shows exactly who is currently attending.
- Settings are in **School Settings**, per venue:
  - (A) **On/Off toggle**. **Off by default.**
  - (B) **Class duration**. Checked-in students automatically "drop off" the live count once this time has passed after check-in. This setting is **global (applies to all venues)**, not per venue.
  - (C) **Maximum capacity** (number of students) **per venue**.
- Logic to rebuild: current occupancy = check-ins at a venue whose check-in time is within the last N minutes (the class duration). Colour = ratio of occupancy to max capacity. The thresholds are not documented; a reasonable choice is green below ~70%, orange up to 99%, red at 100% or more.
- The article also mentions upcoming features: SMS via Twilio, online bookings and events. (Bookings now exist; see the calendar file.)

---

### Kiosk Mode consolidated (from related articles)
Sources:
- https://intercom.help/martialytics/en/articles/5373261-how-to-hide-grading-eligibility-in-kiosk-mode (Grading collection)
- https://intercom.help/martialytics/en/articles/3339583-how-to-set-up-additional-users (related)
- https://intercom.help/martialytics/en/articles/4514994-how-to-manage-bookings (Calendar collection)

- Kiosk Mode is a **self check-in screen** that students use when they arrive for class. It runs on a device at the school, such as a tablet.
- It has a **Kiosk Mode Settings** screen protected by a **PIN**. You must enter the PIN to change settings, which stops students changing them.
- Setting: **Show Grading Details** toggle. When on, a student sees their grading eligibility (traffic-light progress toward the next rank) as they check in. Turning it off hides this. Changes need **Save Settings**.
- **Kiosk settings are stored per device** (local to the device or browser, not account-wide). If you run several kiosks, you have to configure each one separately.
- **Only active students** can check in through Kiosk Mode. Leads and trialists in the Leads CRM can't, so they must be converted to active students first.
- Permissions: the user permission group **"Attendance & POS"** allows checking students in through Attendance and Kiosk Mode, and using the Shop POS.

### Related: user permission groups (from "How To: Set up Additional Users")
- Settings > School Settings > **User Accounts** > **Add New User** > fill in the details (Account Name can be a person or a group label such as "Instructors", plus email) > tick permissions > **Save User**.
- Four permission groups:
  1. **Administration**: Leads, Send, Widgets, Waivers, Shop inventory.
  2. **Income & Billing**: Income Reports, Recurring Plans, Passes, Pending Invoices, Contract Renewals, Member Billing.
  3. **Personal Details**: student personal info, profile Notes, Upcoming Birthdays on the Dashboard.
  4. **Attendance & POS**: check-in through Attendance and Kiosk Mode, Shop POS sales.
- **Default: a new user gets all groups**, and the admin has to untick the ones that don't apply.
- The new user gets an email to confirm their address before they can log in. Permissions can be edited, and the user can be deleted, at any time.


---

## Martialytics Help Centre: Calendar & Bookings collection

Collection URL: https://intercom.help/martialytics/en/collections/2594447-calendar-bookings
The collection page listed 8 article links. The 9th (Squarespace install) was found through search and is included. Screenshots/GIFs (for example "Create class GIF") have no alt text or captions.

---

### 1. How To Video: Use Calendar and Bookings
URL: https://intercom.help/martialytics/en/articles/5567790-how-to-video-use-calendar-and-bookings
- Video plus one line pointing to the Calendar & Bookings guides. No functional text.

### 2. How To: Use the Calendar Feature
URL: https://intercom.help/martialytics/en/articles/4514998-how-to-use-the-calendar-feature
Calendar screen (top navigation "Calendar"):
- **Default view = a day view set to today.**
- **Location filter** dropdown (top-right).
- **Search bar** to find a class on the selected day.
- **Calendar icon** (left) opens a **month mini-calendar** with prev/next arrows. **Days that have classes show a blue dot. Today is highlighted orange.**
- Buttons (right): **Recurring Class** and **One-Off Event** (create).

Class detail popup (click an event):
- Top-left: class name plus **coloured circles showing which ranks are eligible** for the class (rank restriction).
- Below that: day(s) of the week the class runs, start and end time, location, and the date of this instance.
- Top-right action icons:
  - **Email invitation to book** (invite students).
  - **Manually book students in.**
  - **Delete**, with options: **This class only / The class and future classes / All instances of this class.** There is an optional **"notify booked students of cancellation"** checkbox with an optional **reason** text.
  - **Edit**. For a recurring class, edits apply to **all classes in the series**. (The create article says "moving forward".)
- **Capacity bar** shows fill against the class capacity limit. **If no capacity is set, a plain counter of total bookings is shown instead.**
- For **past** classes/events: **Download list of bookings** and **Email all booked students**.

### 3. How To: Create a Recurring Class or One-Off Events
URL: https://intercom.help/martialytics/en/articles/4514932-how-to-create-a-recurring-class-or-one-off-events
- Type choice:
  - **Recurring Class**: runs weekly on set days (for example Junior Karate, Monday and Thursday, 6 to 7 pm). Supports several weekdays.
  - **One-Off Event**: workshops, grading days.
- Form: name plus "form fields" (from other articles these include days, start/end time, location, style/eligible ranks, capacity, price, start/end dates, notifications). Then **Publishing Options**:
  - **Widget & Portal**: bookable through the website widget and the Student Portal.
    - Recurring class with a price: the **pay option appears only in the Widget**. Portal bookers are assumed to already have billing.
    - One-off event with a price: **the price is shown and payment is required in both the Widget and the Portal.**
  - **Widget Only**: for trial or first-timer classes.
  - **Portal Only**: existing students only.
  - **Invite Only**: shown only in the admin calendar. Students need an emailed invitation to book.
- **Start Date / End Date are optional.** With no end date the class repeats indefinitely. (The article's wording is muddled, but the meaning is that dates bound the series.) They can be changed later through Edit.
- **Save Class** button.
- **New-booking notifications are OFF by default.** Turn them on by editing the class and ticking the checkbox. On a recurring class this applies to all future instances.
- After creation, click the event to edit, invite, manually book, or delete (same as article 2).

### 4. How To Video: Bookings Widget (Website)
URL: https://intercom.help/martialytics/en/articles/5567786-how-to-video-bookings-widget-website
- Video plus a pointer to the WordPress/Wix/Squarespace install guides.

### 5. How To: Use the Bookings Widget
URL: https://intercom.help/martialytics/en/articles/4514854-how-to-use-the-bookings-widget
- An embeddable HTML/JS snippet for the school's website so students and leads can book classes.
- Two display modes: **attached to an element (for example a "Book Now" button) that opens it in a modal**, or **embedded inline in a DIV**.
- The code comes from the **Widgets page**. **Clicking the code area copies it to the clipboard.**
- Students can also book through the **Student Portal**.
- In the widget, students can **filter by time of day, location and style**, and **search the calendar for a specific date**.
- After booking: a **confirmation email** with a **cancel link**.

### 6. How To: Manage Bookings
URL: https://intercom.help/martialytics/en/articles/4514994-how-to-manage-bookings
- The class/event booking list shows each person with a **Status**:
  - **Booked In**: confirmed (self-booked through the widget or portal, by invitation, or manually).
  - **Invited**: invitation emailed, waiting for a response.
  - **Declined**: declined the invitation.
- Buttons: **Invite Students** (email a booking link) and **Book Students** (manual).
- **Matching to profiles**:
  - Invitation, Portal and manual bookings are matched automatically to the student profile.
  - Widget bookings are matched automatically **only if first name + last name + email match an Active Student exactly (100%)**.
  - Otherwise a **new Lead is created in the Leads CRM**, and the booking row shows **"Lead" in the Rank column**.
- Row "three dots" menu actions:
  - **Unmatch** from the lead card, then **match to an existing student** (search the student list). Use this when an existing student mistyped their details.
  - **Create New** active student profile from the booking.
  - **Remove Booking** (cancel).
- Trial/new student options: keep them as a lead (track attended classes in lead notes) and later **promote the lead to a student**. Promotion carries over all info, **including card details and signed waivers**. Or create a new student directly.
- **Leads can't use Kiosk Mode.** They must be active students.

### 7. How To: Install the Bookings Widget on WordPress
URL: https://intercom.help/martialytics/en/articles/5213746-how-to-install-the-bookings-widget-on-wordpress
- Needs the **Code Embed** plugin (simple-embed-code).
- On the page editor: Screen Options > enable **Custom Fields** > Enter new > Name `CODEmartialyticsBookingsWidget`, Value = the widget code > Add Custom Field (whitelist any security prompt twice) > put `{{CODEmartialyticsBookingsWidget}}` in the content > Update.
- Support: support@martialytics.com.

### 8. How To: Add the Bookings Widget to Wix
URL: https://intercom.help/martialytics/en/articles/5253077-how-to-add-the-bookings-widget-to-wix
- Wix Settings > Advanced > **Custom Code** > Add Custom Code > paste the widget code > choose the page(s) > Place Code in **"Body - end"**.

### 9. How To: Add the Bookings Widget to your Squarespace website (found through search)
URL: https://intercom.help/martialytics/en/articles/5370881-how-to-add-the-bookings-widget-to-your-squarespace-website
- **Embedded and button/pop-up can't both be used on the same page.**
- Settings > Advanced > Code Injection:
  - Header: the Featherlight lightbox CSS (featherlight 1.7.13).
  - Footer: an async **loader snippet** in Google-Analytics style. It defines the global `ma` command queue and loads `//martialytics.com/widgets/bookings/loader.js`, then calls
    `ma('init', { school: '<SCHOOL ID>', hash: '<HASH>', styles: 'width:450px;height:500px;position:relative;margin:auto;z-index:100000;', element: 'bookings_widget' })`,
    plus jQuery and the Featherlight JS.
  - The School ID and Hash are copied from the widget code on the Widgets page.
- Button mode: an `<a data-featherlight="#bookings_widget">Book a Class</a>` link plus a hidden `<div id="bookings_widget">`.
- Embedded mode: just `<div id="bookings_widget"></div>`.
- Rebuild hint: the widget = a loader JS that injects an iframe or app into the element with id `element`. It is authenticated by the public school ID plus a hash, and its default size is 450x500.


---

## Martialytics Help Centre: Grading & Belt Management collection

Collection URL: https://intercom.help/martialytics/en/collections/194587-grading-belt-management
Collection articles (3). The collection itself does not describe the traffic-light system or the rank editor in text. Both are documented in "How To: Set up your school" (found by searching the help centre), which is included below as a related article.

Screenshots have no alt text or captions.

---

### 1. How To: Use Grading
URL: https://intercom.help/martialytics/en/articles/5567736-how-to-use-grading
- Embedded YouTube video only. No body text.
- Description/tags: "Grading, grading eligibility, belts, ranks."

### 2. How To: Hide grading eligibility in Kiosk Mode
URL: https://intercom.help/martialytics/en/articles/5373261-how-to-hide-grading-eligibility-in-kiosk-mode
- Use case: stop students seeing their own grading eligibility when they check in.
- Flow: open **Kiosk Mode Settings** > enter **PIN** > toggle off **Show Grading Details** > **Save Settings**.
- Default implied: Show Grading Details is ON.
- **Settings are per device.** With several kiosk devices, the change must be made on each one.

### 3. How To: Use the Class Offset feature
URL: https://intercom.help/martialytics/en/articles/3992902-how-to-use-the-class-offset-feature
Two adjustments per student per style:

A) Backdate the start date of the current rank:
1. Open the student profile > section **Styles & Ranks** > click the **Style** to edit.
2. Click the **date field** (current rank start date) to open a calendar date picker.
3. Pick the new date. A **tick appears in the box** to confirm the save. This is an inline auto-save with no Save button.

B) Class Offset (extra classes credited toward eligibility):
1. Same steps 1 and 2 as above, then click the box next to **Class Offset**.
2. Type the number of classes to add. A **tick confirms** it has been applied (auto-save).

Effect: eligibility uses (classes attended since rank start + class offset), and time in rank is counted from the (possibly backdated) start date. This lets schools migrate existing students without entering historical attendance one class at a time.
- Cross-reference: to record actual past attendance, use "Add backdated Attendance records" (Attendance collection).

---

### Related: How To: Set up your school (Styles & Ranks editor + traffic light)
URL: https://intercom.help/martialytics/en/articles/3871121-how-to-set-up-your-school
(Video version: https://intercom.help/martialytics/en/articles/1782187-how-to-video-set-up-your-school)

Onboarding / School Settings page (shown right after sign-up, which comes with a 30-day free trial):
- **School Name**: shown in the top-right corner of the app.
- **Account Information**: owner name, login email, password.
- **Locations**: unlimited. Each has a **Location Nickname** (required and important), which is how the location is referred to across reports and student profiles. They recommend the town or suburb name.

**Styles and Ranks editor**:
- **Style** = a class or programme (for example "Adult Kung Fu"). **Rank** = a position in that style's hierarchy.
- Add a Style name, then add Ranks to it.
- **Auto-colour**: the system assigns a colour to each rank based on its name (for example, "Blue" gives blue). The user can override it by clicking the **coloured tile** next to the rank, which opens a colour picker.
- **Stripes / half belts**: add them as separate ranks. Use the base colour or a slightly different shade, and use the **description field** for the full name (for example "White Belt 1 Stripe").
- **Reorder ranks by drag and drop.**
- **1st rank** = the starting rank (for example White Belt). It is **auto-assigned to a student when they sign up** to that style.
- **Promotion requirements per rank** (what is needed while in this rank to become eligible for the next one):
  - **Number of classes** attended.
  - **Number of days** in rank.
  - An **AND / OR** dropdown that combines the two. Example: White Belt needs 5 classes AND 30 days to be eligible for White Belt 1 Stripe.
- **Save Styles and Ranks** button. Repeat for each style.
- **Ranks are optional.** A style with no ranks (for example "Personal Training") just has participants.
- After the minimum required fields are filled in, the **Get Started** button (top-left) **flashes**. Clicking it opens the Dashboard.

**Traffic light grading status**:
- **Red** = not ready to grade.
- **Orange** = almost ready to grade.
- **Green** = ready to grade (requirements met).
- The exact threshold for "almost" is not documented. A rebuild could use something like 75% or more of the requirement, or within X classes/days. For AND logic, use the minimum progress of the two criteria; for OR, use the maximum.
- Shown in grading views, student profiles, Kiosk check-in (if Show Grading Details is on), and the calendar class popup (rank colour circles). The same colour language is used for capacity tracking and payment statuses.

Implied data model:
- Style {name, ranks[] ordered}
- Rank {name/description, colour, order, req_classes, req_days, operator AND|OR}
- StudentStyle {student, style, current_rank, rank_start_date (editable/backdatable), class_offset (int)}
- Eligibility = f(attendance count since rank_start_date + class_offset, days since rank_start_date, operator)


---

## Collection: Member Billing
URL: https://intercom.help/martialytics/en/collections/194597-member-billing
7 articles. Screenshots in these articles have NO alt text/captions (images only); descriptions below are inferred from surrounding text.

---

### 1. How To Video: Bill your students and process transactions
URL: https://intercom.help/martialytics/en/articles/5567808-how-to-video-bill-your-students-and-process-transactions
- Content is an embedded YouTube video (not transcribed) plus a single line pointing to the Member Billing guides for Stripe and GoCardless.
- Implied scope: billing students (plans/passes) and processing transactions through two payment gateways: **Stripe** (cards) and **GoCardless** (direct debit).

---

### 2. How to: Connect to Stripe
URL: https://intercom.help/martialytics/en/articles/5373334-how-to-connect-to-stripe
- Location: **Member Billing page** (settings area) has a Stripe connect option.
- Two options: **connect an existing Stripe account** OR **create and link a new Stripe account** from that page (Stripe Connect-style onboarding).
- Migration limitation: Stripe does not let third-party apps pull existing billing info/subscriptions. So when connecting an existing account:
  - School must **cancel existing student subscriptions inside Stripe**.
  - Then **re-assign billing (plans) in Martialytics**.
  - Card/billing details must be **re-collected via Martialytics** so they are saved securely against the student profile (done via the Stripe invoice flow — see article 3).

---

### 3. How To: Invoice students using Stripe
URL: https://intercom.help/martialytics/en/articles/5373403-how-to-invoice-students-using-stripe
Purpose: Stripe invoices are sent according to the plan's billing frequency; invoicing is also the mechanism to collect the student's card details remotely, which are then stored on the profile for future charges.

Prerequisite: Stripe account created and connected.

Flow:
1. Open student profile -> **Billing** section -> button **"Add A Plan Or Passes"**.
2. In the assign dialog choose:
   - The **plan or pass** (dropdown of configured products).
   - **Billing frequency**.
   - **Start date** — for **plans only** (passes have no start date field).
3. **Payment Method** dropdown -> option **"Send Invoice (Stripe)"**. (Other options in this dropdown, per GoCardless article: "Direct Debit (GoCardless)"; presumably also card-on-file/cash etc.)
4. Click **"Confirm And Assign Plan To Student"** -> invoice/form is emailed.

Behaviour / states:
- Plan shows on profile with status **Pending** until the student completes the invoice form.
- After payment is taken, status becomes **Active**.
- Payment details captured on the form are stored and **used for all subsequent invoice payments** (auto-charge thereafter).
- **Expiry rule:** the student must complete the invoice form **before the plan's start date**, otherwise the request **expires**. Recommendation: set start date at least **3 days** after sending.
- **Dashboard** shows lists of **pending, upcoming and overdue invoices**.

---

### 4. How To: Use GoCardless + Martialytics
URL: https://intercom.help/martialytics/en/articles/2157720-how-to-use-gocardless-martialytics

Background on GoCardless (direct debit provider, UK):
- Schemes: UK **BACS**, Australia **BECS**, US **ACH**, Eurozone **SEPA**.
- New GoCardless merchant accounts auto-registered on BACS and BECS; ACH/SEPA require contacting GoCardless support.
- **Mandate** = Direct Debit Authorisation; created for a customer during GoCardless checkout. Funds withdrawn after mandate verified; whole process min 3, max 5 days.
- Waiting period: direct debit typically 3–4 business days (5 if first payment needing mandate verification). Settlement usually ≤5 working days.
- Refunds: not native to direct debit; must be requested from the GoCardless dashboard, reviewed case-by-case (not done in Martialytics).

Connecting:
- From Martialytics: enter existing GoCardless account details to connect, or click **Sign Up** button to create a GoCardless account (or sign up on gocardless.com). (Screenshot/GIF shows the connect/setup process.)
- Existing account with mandates -> use the **Sync Mandates** tool (article 5).

Assigning billing:
- Assign a **Recurring Plan** or **Passes Plan** to a student; choose payment method **"Direct Debit (GoCardless)"**. (Screenshots: assign billing dialog; payment method dropdown.)
- If the student has **no mandate**, the admin can **send a mandate request form via email** (screenshot of the request option).
- Student receives an email with a link to a (GoCardless-hosted) form to enter bank details (screenshots of email and form).
- Once set up and verified, the student's bank/mandate details show as **linked** on the profile.
- With a mandate, GoCardless can be used for: **Plans and Passes**, **Shop transactions** (POS), and **One Off payments**.

Tracking payments:
- Student profile -> **Transaction History** area -> list of all transactions for a **selectable date range**.
- Hovering (mouseover) a GoCardless transaction shows its status. Traffic-light colour system:
  - **Green — Paid Out**: payment cleared.
  - **Amber — Pending Submission**: created in GoCardless but not yet submitted to banks; auto-submitted before charge date; **can still be cancelled** while in this state.
  - **Red — Failed**: not collected; reason visible on GoCardless dashboard. A **Retry** button lets admin attempt to take payment again (manual retry; no automatic retry schedule documented).

---

### 5. How To: Sync Mandates from GoCardless
URL: https://intercom.help/martialytics/en/articles/2157876-how-to-sync-mandates-from-gocardless
Purpose: import existing GoCardless mandates and link them to Martialytics students.

Flow:
1. Click **"Sync Mandates"** button (on the GoCardless/member billing settings).
2. System **auto-matches** GoCardless mandates to Martialytics students (matching presumably by name/email).
3. Unmatched: per student, click **"Match Mandate"** and choose from a list of **unmatched existing mandates** (manual matching).
4. Review/confirm matches; to finalise the admin must **re-enter their password** AND **tick a confirmation checkbox** (safeguard against charging the wrong student).

Rules:
- Syncing does **not alter existing subscriptions in GoCardless**; only new transactions created from Martialytics use the mandates.
- Plans must be **recreated in Martialytics** for Martialytics to drive automation.
- Admin should **remove all active plans/subscriptions in GoCardless** first to avoid double billing.

---

### 6. How To: Process a refund through GoCardless
URL: https://intercom.help/martialytics/en/articles/5374074-how-to-process-a-refund-through-gocardless
- No in-app refund for GoCardless. Refunds are requested via the GoCardless dashboard per GoCardless's own "Refunding payments" article. Martialytics just links out.

---

### 7. How To: Remove an expired pass from a student's profile
URL: https://intercom.help/martialytics/en/articles/5373281-how-to-remove-an-expired-pass-from-a-student-s-profile
- **Expired passes are displayed greyed out** on the student profile billing area.
- Click the pass -> **"Remove Plan"** button removes it from the profile.

---

### Rebuild summary (derived)
- Entities: Plan (recurring, has billing frequency + start date), Pass (pack/expiring, no start date), Assignment (student, product, frequency, start date, payment method, status Pending/Active/Expired), Transaction (gateway, amount, status, date), Mandate (GoCardless), stored payment method (Stripe customer/card).
- Payment methods in assign dropdown: Send Invoice (Stripe), Direct Debit (GoCardless) (+ likely others).
- Statuses: Pending (awaiting form) -> Active; request expires if not completed before start date; pass Expired (grey).
- Transaction statuses (GoCardless): Pending Submission (cancellable), Paid Out, Failed (+Retry button).
- Dashboard widgets: pending / upcoming / overdue invoices.
- Transaction History with date-range filter on student profile.


---

## Collection: Point of Sale (Shop)
URL: https://intercom.help/martialytics/en/collections/194604-point-of-sale-shop
1 article.

### 1. How To Video: Use Shop and POS
URL: https://intercom.help/martialytics/en/articles/5567751-how-to-video-use-shop-and-pos
- Subtitle/keywords: "Shop, POS, Point of Sale, stock, inventory management."
- Body = embedded YouTube video only; no text, steps or screenshots published.

Features implied (subtitle + cross-references elsewhere):
- **Shop** product catalogue and **POS** checkout screen.
- **Stock / inventory management** (stock levels per product).
- Shop transactions can be charged via **GoCardless direct debit** against a student's mandate (from the GoCardless article in Member Billing) — so POS sales can be attached to a student and charged to their stored payment method; they appear in the student's Transaction History.

Rebuild notes (derived, not documented): products (name, price, stock qty, possibly variants such as size), cart/checkout, attach sale to student, payment method selection (stored card/Stripe, direct debit, cash), stock decrement on sale, sales recorded as transactions.


---

## Collection: Send (Emails and SMS)
URL: https://intercom.help/martialytics/en/collections/194569-send-emails-and-sms
3 articles.

---

### 1. How To Video: Use Send
URL: https://intercom.help/martialytics/en/articles/5567906-how-to-video-use-send
- Subtitle/keywords: "Automated emails, manual bulk emailing, student communications."
- Body = embedded YouTube video (not transcribed).
- Text: for bulk email to a very **targeted group of students**, use the **Student List** (filter students, then email the filtered set) — links to "How To Video: Navigate your Student List" (https://intercom.help/martialytics/en/articles/5567692-how-to-video-navigate-your-student-list).
- Implied features: **automated emails** (system-triggered), **manual bulk emails**, sending from filtered Student List.

---

### 2. How To: Use Send Bulk SMS
URL: https://intercom.help/martialytics/en/articles/7864287-how-to-use-send-bulk-sms
Location: **Send** module -> **Bulk SMS** tool.

Compose screen:
- Message text box. Bottom-right shows a live counter of **how many SMS segments** the message spans (and thus credits used).
- One SMS segment ≈ **160 characters**; emojis (Unicode) reduce capacity so segments add up quickly (GSM-7 vs UCS-2 encoding).
- Mandatory **opt-out text** auto-appended to every message and **counted in the character count**: `UNSUB https://otsu.io/out` (legal requirement).

Credits:
- SMS costs paid via prepaid **credits** (cost varies by region). No free credits bundled into the subscription.
- Credits **purchased on the Bulk SMS screen**.
- Credits per message vary with character count / emojis (= number of segments) — shown bottom right. (Screenshot of the counter, no alt text.)

Reporting:
- Sent bulk messages appear in a **reports list within Send**; clicking the **message title** opens per-recipient **delivery status**. (Screenshots, no alt text.)

Sender ID:
- Appears from alphanumeric sender **"MARTIALARTS"** where the region supports it; otherwise a **toll-free or long-code number** from a shared pool.

Replies:
- **One-way only**. Replies fail (device shows "undelivered", varies by device); school is not charged for reply attempts.

---

### 3. How To: Add images to emails using Imgur
URL: https://intercom.help/martialytics/en/articles/2349656-how-to-add-images-to-emails-using-imgur
- The Martialytics **email builder** has no native image upload; images inserted by **URL**.
- Email builder toolbar has an **image icon** -> dialog with field **"Link to Image"** and an **Insert** button; image is then rendered in the email body.
- Suggested workflow: upload to Imgur (New post -> Browse/drag-drop -> right-click image -> Copy image address), paste URL into "Link to Image", Insert.
- Rebuild note: a native image upload/hosting in the email editor would be an improvement.

---

### Rebuild summary (derived)
- Send module with: email composer (rich text, image via URL), bulk email to filtered student lists, automated emails, Bulk SMS with segment counter, credits wallet + purchase, mandatory opt-out suffix, alphanumeric sender ID fallback to number pool, per-message delivery report, one-way SMS.


---

## Collection: Waivers
URL: https://intercom.help/martialytics/en/collections/194615-waivers
1 article.

### 1. How To Video: Use Waivers
URL: https://intercom.help/martialytics/en/articles/5567927-how-to-video-use-waivers
- Article subtitle/keywords: "Waivers, creating new waivers, bulk sending, collect signatures remotely."
- Body = embedded YouTube video (not transcribed; no text walkthrough, no screenshots).
- One text line: promotes going paperless via the **Student Form**, which collects new-student details remotely; links to "How To: Send the Student Form" (https://intercom.help/martialytics/en/articles/5075617-how-to-send-the-student-form — in another collection).

Functional features implied by the subtitle (no further detail published in text):
- **Create new waivers** (waiver templates authored by the school).
- **Bulk sending** of waivers to multiple students (email link).
- **Remote signature collection** (student signs online via link; e-signature).
- Related: Student Form for remote onboarding of new students' details.

Rebuild notes (derived, not documented): waiver template (title, body text), send to one/many students, public signing page with signature capture, signed status + timestamp stored on student profile.


---

## Martialytics Help Centre: Data Privacy and GDPR collection

Collection: https://intercom.help/martialytics/en/collections/194622-data-privacy-and-gdpr
Articles in collection: 1.

---

### 1. GDPR Commitment and Roadmap
URL: https://intercom.help/martialytics/en/articles/1892202-gdpr-commitment-and-roadmap

This is mostly a policy statement. The details that matter for the product are below.

#### Roadmap items (all marked COMPLETED)
- Appoint a Data Protection Officer.
- Get legal research and advice.
- Rewrite the **Data Protection Agreement** (DPA).
- Rewrite the **Privacy Statement** and **Cookie Policy**.
- Write a strategy and guidelines, then change the product.
- Update internal processes.
- Test the changes.
- Communicate compliance.
- Create **opt-in tools** to help schools with their own compliance.

#### Product changes stated (requirements for a rebuild)
- **Anonymise more data.**
- **Minimise vendor data sharing.** Share only the fields each third-party vendor needs.
- **Full and permanent data deletion.**
  - In the product, this is a hard delete of a student profile.
  - A separate "Inactive" archive option exists, as the "Delete a Student Profile" article in another collection notes.
- **Student communication opt-ins.**
  - This means consent flags per student for email and marketing communications.
  - Schools must respect these flags in Send / Manual Send.
- **Data export tools** so schools can get their student data out. This relates to the right of access and data portability.

#### GDPR rights the platform should support
- **Right to be forgotten**: permanent deletion.
- **Right to a copy** of the personal data held.
- **Records of processing.**
- **Written agreements with vendors** (DPA).
- **Breach notification** to the authority and, where required, to data subjects.
- **Extra obligations for profiling or monitoring** of people in the EU.

#### Context
- GDPR has applied since 25 May 2018. It also applies to non-EU companies that process EU residents' data.
- Fines can reach €20M or 4% of global revenue.
- Contact address: support@martialytics.com.


---

## Martialytics Help Centre: Leads & Prospects collection (+ One Touch Sign-Ups)

Collection: https://intercom.help/martialytics/en/collections/194562-leads-prospects
Articles in collection: 4.
One Touch Sign-Ups is not in this collection. It lives in "Students & Members" (https://intercom.help/martialytics/en/collections/194554-students-members), and is included at the end as requested.
Screenshots have no alt text, apart from one image in the OTSU article titled "Mmmmmm QR Codes".

---

### 1. How To Video: Use the Leads CRM
URL: https://intercom.help/martialytics/en/articles/5567839-how-to-video-use-the-leads-crm

- The page is a video only. Its text points to the Leads Widget video.

**Leads CRM facts gathered from other articles:**
- Leads are shown as **contact cards** in the Leads CRM.
- Each card has a **notes** section, which also stores answers to extra widget fields.

**Sources of new leads:**
- the website Leads Widget,
- unmatched Bookings Widget bookings (tagged "Lead" in the event's Rank column),
- presumably One Touch Sign-Up prospects.

**Lead to student:**
- A lead can be **promoted** to an active student. Promotion carries over all the lead's info, including card details and signed waivers.
- Leads can be used to track trial attendance through the notes.
- **Kiosk restriction:** a lead cannot use Kiosk Mode. Only active students can.

### 2. How To Video: Install the Leads Widget
URL: https://intercom.help/martialytics/en/articles/1782228-how-to-video-install-the-leads-widget
- The page is a video only and links to the article version below.

### 3. How To: Install The Leads Widget
URL: https://intercom.help/martialytics/en/articles/5408865-how-to-install-the-leads-widget

**Purpose:** an embeddable form that collects prospect details on the school's website. Submissions **automatically create leads** in the Leads CRM.

**Placement:** the snippet can go on any page. The recommended pages are Contact Us or Free Trial Sign-up.

**Widget settings page offers two code versions:**
- **Styled**: ready-made look.
- **Unstyled**: raw HTML/CSS for custom branding.

**Custom fields:**
- Schools can add **extra form fields by editing the code**.
- Answers to the extra fields are saved into the lead card's **notes**. Arbitrary extra fields are therefore stored as notes text.

**Other controls on the settings page:**
- **Send Instructions To My WebMaster** button: emails the code to a third party.
- **Notification email** selector: chooses which email address receives alerts for new leads.
- **Optional redirect URL**: a "thank you" page shown after submission.

**Default fields:** the base fields are not listed in the article. Name, email and phone are implied.

### 4. How To: Export a list of Leads
URL: https://intercom.help/martialytics/en/articles/4222088-how-to-export-a-list-of-leads

**Steps:**
1. In the top nav, open **Send** > **Manual Send** (the bulk email composer).
2. Open the drop-down next to "You're currently sending this email to…".
3. Tick **Select all X leads**.
4. Click the **Export** button (a down-arrow icon). This downloads a **CSV of the leads' contact details**.

**Notes:**
- The export lives in the email composer's recipient picker. There is no separate export screen.
- Custom student lists for export are built in the Student List, which is a different feature.

---

### [Other collection] How To: Use One Touch Sign-Ups (OTSU)
URL: https://intercom.help/martialytics/en/articles/6573388-how-to-use-one-touch-sign-ups-otsu
Subtitle: "Send student forms, streamline onboarding"

#### Concept
- A single public onboarding form that combines, in one flow:
  - **student contact information**,
  - **style selection**,
  - **location**,
  - **billing** (plan purchase),
  - **waiver signing**.
- **Branding:** the form uses the school's logo and colours from the **Student App branding settings**. There is no separate theming.

#### Admin setup
- **Access:** School settings menu > **One Touch Signup**.
- **Multiple forms:** create a new form. A school can have **many** forms.

**Form configuration:**
- **Name** and **Description**.
- **Option toggles** for what the form includes. From the rest of the article, these cover:
  - **Plans offered**: one or several plans the student can choose from.
  - **Passes** plans.
  - An optional **One Off fee**, such as a joining fee.
  - **Waivers**: tick any number of waivers to include. The student signs all the selected ones.
  - Style selection and location selection.

**On save:**
- The system **automatically generates a QR code and a direct, secure link** for the form.
- Schools can print the QR code (posters, in-school signs) or send the link to a prospect.
- The prospect then **completes the sign-up on their own device**.

#### Student flow
- The student opens the link or scans the QR code.
- They fill in contact details, pick a style and location, choose a plan or pass and pay (plus any one-off fee), and sign the waivers.
- The result is presumably an active student with billing attached.

#### Reporting
- **Each form is tracked separately** for **conversion performance**.
  - Example given: compare a link sent to Facebook prospects against a QR code used by walk-ins.
- **Report UI:** a bar chart, one bar per form or metric. **Click a bar, then the download button** to export the list of people behind that bar.


---

## Martialytics Help Centre: Student App collection (iOS/Android)

Collection: https://intercom.help/martialytics/en/collections/3133439-student-app
Articles in collection: 4. Screenshots have no alt text or captions. Article subtitle tags are noted where useful.

---

### 1. How To: Download the Martialytics Student App
URL: https://intercom.help/martialytics/en/articles/6157617-how-to-download-the-martialytics-student-app

- The app is a native app in both public stores. Students find it by searching "Martialytics".
  - iOS: App Store app name "Martialytics Students", id1585747240 (https://apps.apple.com/au/app/martialytics-students/id1585747240)
  - Android: package `com.martialytics` (https://play.google.com/store/apps/details?id=com.martialytics)
- **There is one shared app for all schools, not a white-label app per school.**
  - Per-school branding is applied at runtime from the school's settings (see article 3).
  - The school is identified by the student's login credentials.

### 2. How To: Customise the Student App
URL: https://intercom.help/martialytics/en/articles/6157628-how-to-customise-the-student-app
Subtitle tags: "Whitelabel, Customise, Change Colours, Upload Logo etc"

**Navigation path:** Admin top menu > **Student Portal** (Settings) section > **Settings** button in the page header. This opens the Student App settings. The Portal and the App share one settings area and one access list.

**Branding options:**
- **Colour scheme** for the school.
- **Logo upload**.
- **Background image**: chosen from a set of **predefined background images**, presented as a gallery picker. A custom upload is not mentioned.
- The One Touch Sign-Up forms reuse these same branding settings.

**Access management** (same page):
1. Select the students who should have Portal/App access.
2. Click **Invite**.
3. Each selected student receives an email with a link to **set up a password**.

- **Family grouping:** family members who want shared access must use the **same email address and the same password**. The system groups them on that basis.
- **Revoke** access is available on this page.
- One credential set works for both the web portal and the mobile app.

### 3. How To: Add News Articles to the Student App
URL: https://intercom.help/martialytics/en/articles/6157643-how-to-add-news-articles-to-the-student-app

**Purpose:** news and announcements published into the Student App (a news feed), for example school news, upcoming events and announcements.

**Admin access:** the menu in the top right > **Articles**.

**Articles interface:**
- **Create New Article** button, top left.
- **Article list sorting:** alphabetical, or by **publish date**.

**Article fields:**
- Title.
- Body with **basic rich-text formatting**.
- **Images**: uploaded and **hosted by Martialytics**, using built-in media storage. No external hosting is needed.
- **Publish start date**.
- **Publish end date**, after which the article **auto-expires**. This field is optional.

**Student-side behaviour (implied):** the app shows the articles whose publish window is currently active.

### 4. How To: Use Messages & Chat
URL: https://intercom.help/martialytics/en/articles/6157647-how-to-use-messages-chat

**Overview:** two-way chat between the school (admin) and students who use the Student App.

**Access:** admin top menu **Send** > **Chat** (top right).

**Unread notifications:**
- New student messages show a notification badge on both the **Send** tab and the **Chat** button.

**Chat screen: three-pane layout (admin)**

**Left pane: conversation list**
- Lists the students the school has engaged with.
- Sorted by **latest message first**.
- The selected conversation is highlighted in a lighter colour.

**Centre pane: conversation thread**
- Shows the message history between the school and the student.
- **Reply** box with a **Send** button.
- **Internal notes**: notes added in the thread that the student cannot see.
- **Delete a message:** hover over a message to reveal a **trash icon**.
- Three header icons:
  - **Clock = Snooze.** Hides the conversation until **tomorrow**, **next week** or **next month** (3 presets).
  - **Star = Priority.** Flags the conversation as needing an answer.
  - **Tick = Close.** Marks the conversation done.

**Right pane: student mini-profile**
- **Tags** applied to the student.
- **Styles and Rank** information.
- **Waivers signed** (labelled "soon" in the article).
- Link to **view the full profile**.

**Limit:** the school can **only message students who have the App installed**. Chat is app-only, so a push token or app account is needed.

**Student side:** the student sends and receives messages in the app. Notes are hidden from them.


---

## Martialytics Help Centre: Student Portal collection

Collection: https://intercom.help/martialytics/en/collections/2809106-student-portal
Articles in collection: 2. Help-centre screenshots have no alt text or captions, so only the article text is summarised here.
Cross-referenced articles from other collections that describe what the student sees in the portal are summarised at the end. They live in Calendar & Bookings and are marked [XREF].

---

### 1. How To Video: Use the Student Portal
URL: https://intercom.help/martialytics/en/articles/5567931-how-to-video-use-the-student-portal

- The page is an embedded video only. Apart from the video, the only text is a pointer to the family-login article.
- Its search keywords (from the search snippet) are: "Student Portal, members area, view Study content, class bookings". This confirms the portal's two core functions:
  1. **Class bookings**: the student books themselves into classes and events.
  2. **Study**: the student views the online training content for their rank (see D_study.md).
- The portal is also called the "members area".
- Portal URL: **https://members.martialytics.com**. This is one shared domain for all schools. The school is resolved from the account the student logs in to, not from a per-school subdomain.

### 2. How To: Set up a Student Portal login for families
URL: https://intercom.help/martialytics/en/articles/4619219-how-to-set-up-a-student-portal-login-for-families

**Concept:** Several student profiles that share one email address (a family or group) can use one portal login. That login can book classes for, and view Study content for, every linked student.

**Admin flow:**
1. On the admin "Student Portal" page (Settings menu > Student Portal), select each student in the family and click **Invite**.
2. The system sends **one invitation email per invited student profile** to the shared email address. For example, inviting 3 students sends 3 emails.

**Family member flow:**
3. The email owner must accept **every** invitation and enter **the same password each time**.
   - Implied rule: identity is the email address plus the password. Profiles that share an email and password are grouped under one login. A different password would presumably create a separate login.
4. After accepting all the invitations, they log in at members.martialytics.com.
5. **Profile selector:** when booking classes or viewing Study content, the user picks which linked profile they are acting as.

**Rebuild implications:**
- Model this as a user account (email + password) linked to many student profiles (1 to N).
- Invitations are per profile. Each invite token sets or confirms the password for that profile's link.
- The portal UI needs a "select student/profile" control on the booking and Study screens.

---

### [XREF] Student-facing booking behaviour (from Calendar & Bookings articles)

#### How To: Use the Bookings Widget
URL: https://intercom.help/martialytics/en/articles/4514854-how-to-use-the-bookings-widget
- **Booking channels:** students can book either through the website Bookings Widget or by logging in to the Student Portal.
- **Booking screen filters** (widget and portal): **time of day**, **location** and **style**. Students can also **search the calendar for a specific date**.
- **Confirmation email:** after booking, the student receives an email confirmation that contains a **cancel link**, so they can cancel if they can't attend.
- **Widget embed:** an HTML snippet copied from the admin Widgets page. Clicking the code area copies it to the clipboard. The snippet can either:
  - attach to any element, such as a "Book Now" button that opens a modal, or
  - be embedded in a DIV on a landing page.

#### How To: Create a Recurring Class or One-Off Events
URL: https://intercom.help/martialytics/en/articles/4514932-how-to-create-a-recurring-class-or-one-off-events

**Event types:**
- **Recurring Class**: weekly schedule, for example Mon and Thu 6-7pm.
- **One-Off Event**: for example a workshop or grading day.

**Publishing Options** (these control where a class is visible and bookable):

| Option | Where it can be booked | Pricing behaviour |
|---|---|---|
| Widget & Portal | Website Widget and Student Portal | Recurring class: a price is collected **only in the Widget**, because portal students are assumed to already have billing. One-off event: the price shows in **both** Widget and Portal, and the person must pay to book. |
| Widget Only | Website Widget only | Intended for trial or first-time attenders. |
| Portal Only | Student Portal only | Intended for existing students only. |
| Invite Only | Not publicly bookable; visible only in the admin calendar | Students book through an emailed invitation. |

**Other settings:**
- Start and End dates are optional and can be edited later.
- New-booking notifications are **off by default**. They are turned on per class with a checkbox. On a recurring class, the setting applies to all future occurrences.
- Admin actions on a calendar event:
  - invite students by email to book
  - book students manually
  - delete
  - edit (edits to a recurring class apply to all future classes)

#### How To: Manage Bookings
URL: https://intercom.help/martialytics/en/articles/4514994-how-to-manage-bookings

**Booking statuses:**
- **Booked In**: booked through the widget, the portal, an invitation, or manually by the admin.
- **Invited**: an email invite has been sent and no response has come back yet.
- **Declined**: the student declined the invite.

**Matching bookings to profiles:**
- **Automatic match:** portal, invitation and manual bookings are matched to the student profile automatically.
- **Widget matching rule:** a widget booking auto-matches only if the **first name, last name and email are a 100% match** with an **Active** student.
- **No match:** otherwise the system **creates a new Lead** in the Leads CRM, and the calendar event's Rank column shows "Lead".

**Admin fixes for unmatched bookings:**
- **Unmatch and rematch:** use the three-dot menu to unmatch the booking from the lead card and match it to the student's existing profile.
- **Create New:** the three-dot menu can create a new active student profile from the booking.
- **Remove Booking:** the three-dot menu option that cancels the booking.

**Leads and trial students:**
- A lead can be **promoted** to an active student. Promotion carries over all the lead's info, including **card details and signed waivers**.
- Kiosk Mode booking or check-in only works for **active** students. A trial student who should use the kiosk must be added as an active student.


---

## Martialytics Help Centre: Study collection

Collection: https://intercom.help/martialytics/en/collections/2286434-study
Articles in collection: 3. Screenshots have no alt text.

---

### 1. How To Video: Use Study
URL: https://intercom.help/martialytics/en/articles/5567740-how-to-video-use-study
- The page is an embedded video only. Its text links to the written article below.
- Keywords: Study, Student Portal, online training, remote training.

### 2. How To: Use Study
URL: https://intercom.help/martialytics/en/articles/3838100-how-to-use-study

**Purpose:** a curriculum library of online training material for each rank, delivered to students through the Student Portal (and the App).

#### Admin side
- **Access:** Study item in the top navigation bar of the Dashboard.
- **Style picker:** a drop-down at the top selects the martial-arts style. Content is organised by **Style, then Rank**.
- **Page layout:** the page lists the style's ranks. Each rank section has an **Add New Item** button.

**Add New Item form:**
- **Title**.
- Content, as one of:
  - **upload a PDF**,
  - **upload an image**, or
  - **insert a link to a training video**, typically a YouTube URL that is embedded.
- **Save Content** button.

**Other behaviour:**
- **Ordering:** items show as **tiles** that can be reordered by **drag and drop**. The order is saved.
- **Recommendation:** link YouTube videos as **Unlisted**, so only portal users see them.

**Granting access:**
- **Invite:** Settings menu > **Student Portal**. Search for individual students, or **select all**, then invite.
- **Revoke:** click the orange **Revoke** button next to a student's name. Access can be revoked at any time.

#### Student side ("STUDENT PORTAL SECTION")
- **Invitation:** an invited student receives an email with a link to **create a login**.
- **Visibility rule (key):** a student sees the training material for their **current rank plus all lower ranks** in that style. Higher ranks are hidden.
  - **To make content visible to everyone** in a style, upload it to the **lowest rank**.
- **Multiple styles (implied):** visibility is evaluated per style, using the student's rank in that style.
- **Families:** a family login selects which profile is viewing Study content.

### 3. How To: Make your YouTube videos 'unlisted'
URL: https://intercom.help/martialytics/en/articles/3845235-how-to-make-your-youtube-videos-unlisted

**Steps on YouTube:**
1. Open **My Videos**.
2. Select the video and click **Edit**.
3. In the Privacy section, choose **Unlisted** (not Public or Private).
4. Click **Save Changes**.
5. Paste the link into Study.

**Rebuild note:** Study does not host video. It stores links or embeds to external video, while PDFs and images are uploaded. A rebuild could add native video hosting as an improvement.

