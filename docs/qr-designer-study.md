# Styled QR designer — study and proposal

*2026-10-20 · read-only study, nothing implemented. Line numbers are as of this date.*

## Decisions (2026-10-20, the user asked Claude to decide)

1. **Encoder:** `qrcode-generator` (MIT), bundled by Vite. It is needed for a live editor preview and for the standalone QR element. The server keeps bacon for every non-designer QR.
2. **Badge QR target:** the **badge-verify page** (`/verify/badge/{token}`). It proves the badge is genuine to anyone at the door, and a removed official gets the same "not valid" answer as an unknown code. The members-only event page proves nothing to a stranger.
3. **Test tools:** `vitest` and `jsqr` are accepted as dev-only dependencies; they never reach the bundle.
4. **The 7 unrelated QR bugs:** fixed **first and separately**, as small low-risk changes, before Phase 1. Draft certificates get a clearly-marked non-verifying QR.
5. **TakeOne preset logo:** the **host's** logo, i.e. the organiser (club or federation). The club logo stays available as a choice in the Logo section.

---

## Part 1 — What we have

### 1. Stack

| Layer | What | Where |
|---|---|---|
| Backend | Laravel `^12.0`, PHP 8.2 | `composer.json:31` |
| QR encoding | `bacon/bacon-qr-code` **3.0.4** (server only) | `composer.json:27`, `composer.lock:56` |
| Designer front end | React 18.3.1 "islands" mounted into Blade pages | `package.json:24-25`, `resources/js/island.js:20-28` |
| Build | Vite ^7 + @vitejs/plugin-react; `designer.jsx` and `printables-print.jsx` are separate entries | `vite.config.js:23-24` |
| State | Own small store: `get/set/subscribe/commit/begin/end/undo/redo`, read via `useSyncExternalStore` | `designer/store.js:1-123` |
| Canvas rendering | **Plain DOM**: each element an absolutely-placed `<div>`; text = DOM text, shapes/lines = inline `<svg>`, pictures = `<img>` | `designer/Renderer.jsx:392-420`, 221-235, 254-308, 367-390 |
| One renderer for screen and paper | `PageView` (`Renderer.jsx:422-428`) is used by the editor **and** the print page | `printables-print.jsx` |

### 2. QR generation today

All server QRs use bacon 3.0.4. **No call sets an error-correction level → every QR is level L** (`vendor/bacon/bacon-qr-code/src/Writer.php:42-43`). No QR library in `package.json`, none vendored in `public/`.

| Site | Side | Output | Margin | Encodes |
|---|---|---|---|---|
| `App\Support\Qr::svg` — `app/Support/Qr.php:31-64` | server | inline SVG, colours by string replace | 1 | caller's string |
| `<x-qr-code>` — `components/qr-code.blade.php:42, 334-348` | server SVG 512 / client PNG 1024 | SVG/PNG | 1 | club, register, member, event, activity URLs |
| `QrController` posters / `memberSvg` — `QrController.php:133-166` | server | SVG 600 / 256 | 1 | same; logo printed *beside* the code |
| **Certificates** — `CertificateService::qrDataUri`, `packages/ahl/certificates/src/CertificateService.php:520-523` | server | **PNG data URI, 220px**, black on white | 1 | `/verify/{verify_token}` |
| **Badges** — `EventBadges::designValues` `app/Events/Support/EventBadges.php:268-285`; `EventParticipantBadges.php:82,101` | server | same 220px PNG | 1 | the **members-only event page** `me.events.show` |
| Screen pairing — `components/screen-pairing.blade.php:443` | server | SVG 400, `#141010` | 4 | `/screen/claim/{code}` |
| Court screens — `components/court-screens.blade.php:691,765` | server | SVG 150 / 224 | 2 / 1 | `/screen`, `/camera` |
| OpenMat — `OpenMatController.php:275` | server | SVG 220 | 1 | `/openmat/join/{code}` |
| Federation invite — `FederationApiController.php:551-568` | server | PNG 1024 / SVG 512 | 4 / 2 | invitation URL |
| 2FA — `app/Members/Support/TwoFactor.php:108-113` | server | SVG 200 | 4 | `otpauth://` |
| Flutter camera — `flutter/TV/lib/src/camera/station.dart:1320` | device | `qr_flutter` 4.1.0 | — | claim URL |
| Scanner — `partials/qr-scanner.blade.php:135-257` | client | native `BarcodeDetector`, own hosts only | — | — |

**What a certificate QR proves:** `verify_token` = `Str::random(32)` on create (`packages/ahl/certificates/src/Models/Certificate.php:48`), unique; not signed, no expiry; revocation = status. Route `/verify/{token}` `[A-Za-z0-9]{32}`, `throttle:30,1` (`app/Certificates/routes.php:12-15`); page `app/Certificates/Controllers/VerifyController.php:26-80` (record, revoked state, event story, team roster; noindex + no-referrer).

**Styling today:** `Qr::svg` size/margin/dark/light only (colours used by screen pairing alone). No logo in the code, no gradient; certificates/badges always black on white.

**Bugs/limits found (separate from this proposal):**
1. **Draft certificates print a QR that verifies nothing** — `CertificateService.php:420-421` encodes token `'sample'`, which fails the 32-char route constraint → 404.
2. **`QrController::eventUrl()` returns the members-only page in both branches** (`QrController.php:75-80`) — signed-out scanners of a public event hit a login wall.
3. **Badge QRs go to the members-only event page**; `EventBadges::verifyUrl()` / `/verify/badge/{token}` (`BadgeVerifyController.php:23-63`) is unreachable.
4. **`camera/web.blade.php:168` calls `window.TakeoneQr`, defined nowhere** → that QR never shows.
5. The package registers a JSON route with the same name `certificates.verify` (`packages/ahl/certificates/routes/public.php:8`).
6. **Everything is EC level L** — no headroom for a logo, least robust in print.
7. **Certificate/badge QRs are 220px PNGs** — blurry when printed large.

### 3. The "Verification QR" live element

- **Defined** in `CertificatePrintable::fields()` (`app/Certificates/Support/CertificatePrintable.php:171-213`, image fields 207-211, sample `''`); label `printables::messages.field.qr`.
- **Element** = `image` with `field:"qr"`, `fit`, `radius`, `stroke` (`designer/library.js:262-268`; sanitizer `DocumentSanitizer.php:172-203`).
- **Editor**: always a **fake** 21×21 pattern (seeded pseudo-random + finder rings, `#0f172a`) — `Renderer.jsx:322-339`. *Preview* swaps text tokens only (`Renderer.jsx:101`); the QR stays fake (374-378).
- **Print**: every field gets a value (`Printer.php:44`); certificates `qr` = the 220px PNG (`DesignedCertificates.php:44`), badges via `EventBadges::designValues` (270); drawn as `<img>` (`Renderer.jsx:378-380`).

### 4. Data model

- `printable_designs` (`app/Printables/database/migrations/2026_10_19_000001_create_printable_designs_tables.php:25-45`): uuid, kind, owner morph, slot, title, format, orientation, mm size, **`document` JSON**, `revision`; unique (kind, owner, slot). Pictures in `printable_assets` (47-60).
- Document `{version:1, pages:[{id, background, elements}]}`; element props `designer/model.js:1-16`.
- **Sanitizer is a whitelist** — unknown keys dropped (`DocumentSanitizer.php:17`), `version` always 1 (61), image props `asset|field|graphic, colors, fit, radius, stroke` (172-203). **A `qrStyle` would be stripped today.**
- Autosave: 1 s after the last change, after gestures (`persistence.js:201-218`); revision-checked PUT, 409 → *Load theirs / Keep mine* (`DesignerController.php:149-177`).
- Undo/redo: 150 snapshots, gestures grouped (`store.js:17-99`).
- No document migrations exist; back-compat is by **defaults** (absent prop = old behaviour).

### 5. Editor UI

- Contextual toolbar per element (`Toolbar.jsx:278-374`; image: field chip, FitSwitch, Corners, Border, Effects, Flip, Transparency, Position — 337-349). Bigger options in **side panels** (`panels.jsx` SidePanel 16) or **popovers** (`ui.jsx` PopButton 98).
- Reusable: Slider (`ui.jsx:189`), NumberField (217), MenuList (154), Tip, Icon, toast, useConfirm; `ColorPanel` (`panels.jsx:216-291`) with HSV picker, eyedropper, doc colours and a **two-stop linear gradient** (shape fills only); tile grids (ElementsPanel 441+); UploadsPanel (500).
- i18n `t()` (`context.js:10-19`) from `printables::messages.ui` — **en only**; RTL via `boot.dir` (`DesignerController.php:414`) and `dir="auto"`.

### 6. Print pipeline

- **Headless Chromium on our server, JavaScript runs** (`ChromeRenderer.php`): React print page draws the same `PageView`, waits for fonts, `--print-to-pdf` (DOM/SVG vector, images raster) or PNG at dpi/96. **No network** (`--host-resolver-rules=MAP * ~NOTFOUND`), file:// assets, 15 s virtual time, 90 s timeout, one retry, completeness check (87-103).
- Pictures arrive as **data URIs** (`Printer.php:153-158`); fonts/bundle as file://.
- Bulk: `BuildCertificateBundle` on queue `media` (1200 s, 1 try); one Chromium run per page + `pdfunite`; badges 4×A6/A4 at 300 dpi (`Printer::sheets`). ≈0.5–2 s per page.

### 7. Tests & quality

PHPUnit (sqlite memory); `tests/Feature/Events/PrintablesTest.php` (15 tests). **No JS tests, no eslint.** Pint (Laravel preset). CI `.github/workflows/ci.yml`: migrate, `npm run build`, `php artisan test`, Pint (non-blocking).

---

## Part 2 — Proposal

### 1. Renderer — one JavaScript module for editor, Preview and Print

`resources/js/islands/designer/qr/` — `renderQr(text, style) → SVG`, used by `ImageElement` for the `qr` field (and later a standalone `qr` element).

**Why JS:** print already runs our React renderer in Chromium (§6), so one module gives identical output on canvas, in Preview and on paper — as text and shapes do today. A PHP renderer would be a second copy that drifts.

**Value contract:** the server sends the **text to encode** (`qr_text`, the verify URL) beside today's `qr` PNG; the renderer draws **vector SVG** (sharp at any size). No `qr_text`/no style → old PNG path.

**Encoder:** recommend **`qrcode-generator`** (MIT, no deps, ~20 KB) via npm, bundled by Vite (self-hosted). It returns the module matrix for EC L/M/Q/H; all shaping/drawing is ours. Alternative with no new JS dependency: server sends bacon's **matrix** — fine for certificates/badges, but live editor preview and a "type a URL" element would need a server round-trip. *(Open question 1.)*

### 2. `qrStyle` schema

```json
"qrStyle": {
  "v": 1,
  "body": "square", "eyeFrame": "square", "eyeBall": "square",
  "eyes": { "tl": {"frame":null,"ball":null,"frameColor":null,"ballColor":null,"flip":false}, "tr": {}, "bl": {} },
  "bodyColor": "#000000",
  "bg": "#ffffff",
  "gradient": null,
  "gradientOnEyes": false,
  "eyeFrameColor": null, "eyeBallColor": null,
  "logo": null,
  "margin": 1,
  "ecLevel": "auto"
}
```

- `bg`: a colour or `"transparent"`.
- `gradient`: `{type: linear|radial, angle, stops: [[colour, 0..1], …]}`.
- `logo`: `{src: "asset:<uuid>" | "field:club_logo" | "field:logo", size: 0.10–0.30, clearBehind, padding, shape: square|circle}`.
- `ecLevel`: `auto` = M, raised to H when a logo is set; otherwise L|M|Q|H.

**Old elements are unchanged:** no `qrStyle` → renderer not involved, the PNG `<img>` path draws exactly as today. The sanitizer gains a `qrStyle()` whitelist: shape ids from a fixed list, colours through the existing `color()`, numbers clamped, `logo.src` only this design's asset or this kind's image field. Document `version` stays 1 (additive).

### 3. Shapes (initial set)

- **Body (13):** square (classic), dots, small squares, diamond, star, plus, vertical bars, horizontal bars — independent; **rounded, extra-rounded, classy, classy-rounded, liquid** — *neighbour-aware* (a corner rounds only where it has no dark neighbour).
- **Eye frame (10):** square, rounded, extra-rounded, circle, leaf*, leaf-inverse*, cushion, dotted, double-line, shield*.
- **Eye ball (10):** square, rounded, circle, leaf*, leaf-inverse*, diamond, star, cushion, flower, dots-cluster.

\* **Mirrored per corner** so the point faces the code's centre (TL as drawn, TR flipped horizontally, BL vertically); `eyes.*.flip` overrides.

### 4. Editor UI

- QR element selected → a **"QR style"** toolbar button → side panel: **Presets** (tiles) · **Shapes** (three tile grids rendered by the same module) · **Colours** (reuse `ColorPanel`, gradient extended with radial) · **Eyes** (per-eye colours/flip, collapsed) · **Logo** (Uploads picker or live field: club logo / host logo) · **Margin**, **Error correction** (Slider, MenuList).
- **Presets:** Classic (today) · Rounded · Dots · Liquid · Classy · **TakeOne** (#6d5bd0 gradient, rounded eyes, host logo).
- **Scannability guard** (live warning chips + red badge on the element): contrast < 4:1, inverted colours, logo too large for the EC level (>25% width), margin < 2, printed smaller than ~18 mm.
- The editor shows a **real sample QR** (encoding the field's sample URL) instead of today's fake pattern.
- **Standalone static QR element** (any URL/text) with the same renderer — Phase 3; needs the client encoder.
- Arabic: new `ar` messages file; panel mirrors with `dir`.

### 5. Print / export

- QR as **inline SVG** → vector in the PDF; badges' 300 dpi sheets stay crisp.
- Logo from the design's assets / live image field — both already data URIs at print; no network.
- `DesignedCertificates` and `EventBadges` add `qr_text`; `qr` (PNG) kept for old designs.
- Small related fix: draft certificates should carry a clearly-marked non-verifying QR, not a 404 URL.

### 6. Testing

- **Unit (vitest, dev only):** shape paths for known neighbour patterns, per-eye mirroring, margins, EC choice, `qrStyle` absent → null.
- **Scan test, every combination must decode:** a dev-only page renders body × frame × ball × {solid, gradient, logo at max size} for several payloads (short URL, long token URL, Arabic) and decodes each with **jsQR** (MIT, dev only) — run in **our own Chromium** (`ChromeRenderer`) from `tests/Feature/Printables/QrScanTest.php`, reading the pass/fail table the page writes. A non-decoding combination fails CI; the guard's limits come from what passes.
- **Feature:** sanitizer keeps/cleans `qrStyle`; values carry `qr_text`; old designs unchanged.

### 7. Files, compatibility, risks, rollout

**Create:**

| File | Purpose |
|---|---|
| `resources/js/islands/designer/qr/encode.js` | Encoder wrapper → matrix + EC. |
| `…/qr/shapes.js` | Body / frame / ball path builders, neighbour logic, mirroring. |
| `…/qr/render.js` | `renderQr` → SVG (colours, gradients, logo knock-out, margin). |
| `…/qr/presets.js` | The presets. |
| `…/qr/guard.js` | Scannability checks. |
| `resources/js/islands/designer/QrPanel.jsx` | The side panel. |
| `app/Printables/resources/lang/ar/messages.php` | Arabic strings. |
| `tests/js/qr/*.test.js` | Unit tests. |
| `tests/Feature/Printables/QrScanTest.php` + `resources/js/islands/qr-scan-test.jsx` | The all-combinations scan test. |

**Modify:**

| File | Change |
|---|---|
| `designer/Renderer.jsx` | QR field → `renderQr`; the placeholder becomes a real sample QR. |
| `designer/Toolbar.jsx` | "QR style" button. |
| `designer/panels.jsx` | Register the panel; reuse the gradient, adding radial. |
| `designer/library.js` | New QR elements get the Classic `qrStyle`. |
| `app/Printables/Support/DocumentSanitizer.php` | `qrStyle` whitelist. |
| `app/Certificates/Support/DesignedCertificates.php`, `app/Events/Support/EventBadges.php` | Add `qr_text`. |
| `app/Certificates/Support/CertificatePrintable.php` | Sample `qr_text` for the editor. |
| `app/Printables/resources/lang/en/messages.php` | Strings. |
| `package.json` | `qrcode-generator`; dev: `vitest`, `jsqr`. |

**Compatibility:**
- Absent `qrStyle` = today's PNG path, untouched.
- No DB migration.
- `qr` stays beside `qr_text`.
- Feature flag `PRINTABLES_QR_STYLE` (default off) guards the panel and the new path.

**Risks / open questions:**
1. JS encoder dependency vs server-sent matrix.
2. Badge QR target: the members-only event page as today, the badge-verify page, or the public event page?
3. Styling can break scanning. Mitigated by EC auto-raise, the guard, and the scan test gating CI.
4. Old phones handle gradients and inverted codes worse. The guard warns; Classic stays the default.
5. Print cost: an SVG QR is lighter than today's PNG.
6. The 7 unrelated QR bugs: fix separately or alongside?

| Phase | Scope | Effort |
|---|---|---|
| 1 | Encoder, renderer, 13/10/10 shapes, solid colours, margin, EC, sanitizer, `qr_text`, panel (shapes + colours), vector print, unit tests | **4–5 days** |
| 2 | Gradients (linear/radial), per-eye colours/flips, presets incl. TakeOne, all-combinations scan test | **~3 days** |
| 3 | Logo (upload + live field, knock-out, auto EC H), scannability guard, standalone QR element, Arabic strings | **3–4 days** |
