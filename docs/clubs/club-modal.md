# The club modal — `<x-club-modal>`

The five-step form that creates or edits a club. Checked against the code on
31 Oct 2026.

## Main files

- `resources/views/components/club-modal.blade.php`: the shell. An Alpine
  component (`clubModalController`) with step navigation, per-step validation,
  the draft, and the submit.
- `resources/views/components/club-modal/tabs/`: `basic-info`,
  `identity-branding`, `location`, `contact`, `finance-settings`.
- `app/Clubs/Controllers/ClubApiController.php`: store, update, and the lookups
  the form calls.
- `app/Clubs/Controllers/BusinessClubController.php`: store for the business
  dashboard. Both use `App\Clubs\Support\ClubCreationService`.

## Where it is used

| Host | Props |
|---|---|
| `/admin/clubs`: `platform::clubs.index`, `platform::mobile.clubs` | `mode="create"`. Edit opens on the same instance. |
| Business dashboard, desktop and mobile | `mode="create" context="business"` |
| Public club page `clubs::public.show`, super-admin only | `mode="edit" :club` |

Open it with `window.dispatchEvent(new CustomEvent('open-club-modal', {detail: {mode: 'edit', clubId}}))`.
Close it with `close-club-modal`.

## The five steps

1. **Basic info.** Name (fills the slug), owner, established date, slogan,
   description, commercial-registration and VAT numbers, VAT %, and the registration
   requirements and terms (rich text, with an Arabic version through
   `<x-fields.language-toggle>`). The owner is found through
   `<x-fields.member-search>`, which calls `admin.api.users.lookup`. In
   `context="business"` the owner is the signed-in user and this check is skipped.
2. **Identity and branding.** Slug and URL preview, a QR code of the club URL
   (download and print), logo, cover image, the registration-wizard splash image
   (with a phone-frame preview), and social links.
3. **Location.** `<x-fields.country>`, `<x-fields.timezone>`,
   `<x-fields.currency>` and `<x-fields.location-map>`, plus "use my location"
   (browser geolocation) and "centre on country".
4. **Contact.** Email and phone, either the owner's or the club's own
   (`<x-fields.phone-code>`).
5. **Finance and settings.** Bank accounts (bank, account name, account number,
   IBAN, SWIFT, BenefitPay), club status, public-profile switch, registration fee
   and enrolment fee.

## Data flow

- **Edit loads** with `GET /admin/api/clubs/{id}`.
- **Submit:** `POST /admin/clubs`, `PUT /admin/clubs/{id}`, or, in business
  context, `POST /business/clubs`. The `/admin` routes are `role:super-admin`
  (`app/Clubs/routes.php`). The business route needs an approved business
  (`business` middleware).
- **After saving**, the modal dispatches `club-saved` `{mode, club}` on `window`,
  and the hosting list patches itself. A **create** then navigates to
  `/admin/club/{slug}`; an **edit** stays on the page.
- **Bank account number and IBAN** are encrypted at rest (`ClubBankAccount`
  accessors, keyed by `APP_KEY`).

## Rules and gotchas

- **No nested modals.** A second modal opened from inside this one (the old
  Bootstrap user-picker and cropper modals) closed the club modal, threw away the
  typed data, and produced the "aria-hidden on a focused element" warning. Pickers
  and croppers inside the modal are **in-modal overlays**: the owner picker
  (`.user-picker-overlay`) and the logo, cover and splash crop sheets
  (`#logoCropperOverlay`, `#coverCropperOverlay`, `#splashCropperOverlay`,
  `.cropper-sheet`). Keep it that way when adding a picker.
- **The draft.** Create mode saves the form to `localStorage` under
  `clubModalDraft:{context}` on every step change. Admin and business drafts
  never mix; the old unscoped key `clubModalDraft` is removed on load. Pictures
  (too big for `localStorage`), rich-text fields, `translations[...]` and tokens
  are skipped. A file input can never be given a value from a draft.
- **Validation** runs per step before moving forward and shows one toast
  summarising the missing fields. On a 422 the server's errors are mapped back to
  their step.
- **The enrolment fee is on step 5.** An early note said the modal had no
  enrolment fee field; that is no longer true.

## Limitations

- **It brings its own cropper.** The identity step loads Cropme 1.4.1 and runs
  its own crop sheets, so it does not use `<x-takeone-cropper :inline>`. That goes
  against CLAUDE.md *One Cropper Everywhere* on mobile.
- **Two scripts come from CDNs:** `qrcodejs@1.0.0` (jsDelivr, in
  `club-modal.blade.php`) and `cropme@1.4.1` (unpkg, in `identity-branding`).
  Both go against *Self-Host Everything*. The rest of the product draws QR codes
  server-side (`App\Support\Qr`, `<x-qr-code>`).
- **It is Bootstrap-class markup** (`form-control`, `input-group`, `btn`)
  rendered by the compatibility layer in `app.css` (see `frontend-css.md`).
