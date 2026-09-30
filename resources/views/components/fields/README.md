# Fields — the shared form controls

Every generic input the platform asks a person to fill in, in one folder, so
any module (Clubs, Members, Events, Shop, Federations…) uses the SAME control
for the same question. Called as `<x-fields.NAME>`.

Moved here and renamed on 2026-09-23 (old tag → new tag):

| Tag | Was | What it asks for |
|---|---|---|
| `<x-fields.date-of-birth>` | `<x-birthdate-dropdown>` | Day / month / year with a live age badge |
| `<x-fields.gender>` | `<x-gender-dropdown>` | Gender as a dropdown (`Male` / `Female`) |
| `<x-fields.gender-toggle>` | `<x-gender-toggle>` | Gender as two buttons (Alpine-bound) |
| `<x-fields.country>` | `<x-country-dropdown>` | A country — nationality, or a club's country |
| `<x-fields.phone-code>` | `<x-country-code-dropdown>` | Phone calling code, with flags |
| `<x-fields.currency>` | `<x-currency-dropdown>` | Currency |
| `<x-fields.timezone>` | `<x-timezone-dropdown>` | Timezone |
| `<x-fields.blood-type>` | `<x-blood-type-dropdown>` | Blood type |
| `<x-fields.marital-status>` | `<x-marital-status-dropdown>` | Marital status |
| `<x-fields.relationship>` | `<x-relationship-dropdown>` | Family relationship |
| `<x-fields.select>` | `<x-select-menu>` | Any short list — the replacement for a native `<select>` |
| `<x-fields.date>` | `<x-date-picker>` | A calendar date — the replacement for `<input type="date">` |
| `<x-fields.weekly-schedule>` | `<x-schedule-time-picker>` | Days of the week + start/end time |
| `<x-fields.rich-text>` | `<x-rich-text-editor>` | Formatted text (output is untrusted HTML — sanitise server-side) |
| `<x-fields.location-map>` | `<x-location-map>` | A place on a map, with address search |
| `<x-fields.language-toggle>` | `<x-lang-toggle>` | Which language a translatable field is being typed in |
| `<x-fields.social-links>` | `<x-social-links-editor>` | A list of social links |
| `<x-fields.member-search>` | _(new, 2026-09-24)_ | Find a TakeOne member by name, phone, email or TakeOne ID; fires a bubbling `member-picked` with the result. Endpoint should use `App\Members\Support\MemberLookup` |
| `<x-fields.person-picker>` | _(new, 2026-09-24)_ | A person, by EMAIL or by FINDING A TAKEONE MEMBER — both post the same email (+ optional name) input. Needs a `search-url` answering `{people:[{name,email,photo}]}` |

Props are unchanged by the move — see the `@props` block at the top of each
file, and the component catalogue in `CLAUDE.md` (Part 5).

## What they depend on

Inside this folder (`support/`, included by the fields that need them):

- `support/float-panel.blade.php` — `window.tkFloat`, the dropdown panel that
  floats above scrolling sheets. Used by `date-of-birth`, `country`,
  `phone-code` (and by two event partials).
- `support/location-map-runtime.blade.php` — the map loader for
  `location-map` (also included by the two mobile shell layouts).
- `support/social-link-row.blade.php` — one row of `social-links`.

Outside this folder, because they are not views:

- `App\Support\Countries` + `public/data/countries.json` — the one country
  list (`country`, `phone-code`, `currency`, `timezone`).
- `App\Support\Flag` + `public/images/flags/` via `<x-flag>` — flags in
  `phone-code` / `country`.
- Routes: `me.geo.country` (`country` guesses the visitor's country),
  `me.geo.map-link` (`location-map`'s opt-in Google Maps link box follows a
  short link to its pin, via `App\Support\MapLink`) and
  `/ai/compose` (the `rich-text` magic wand).
- Alpine.js, loaded by the base layouts.
- ⚠️ `location-map` still loads Leaflet from `unpkg.com` — against the
  Self-Host Everything rule; to be vendored into `public/`.

## Rules

- A new generic input goes HERE, named for the question it asks, not the
  widget it happens to be (`date-of-birth`, not `birthdate-dropdown`).
- Anything specific to one module stays in that module.
- No native `<select>` / `<input type="date|time">` (Design Rule #4).
