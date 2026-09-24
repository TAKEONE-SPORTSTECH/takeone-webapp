# Vendored libraries for the federation dashboard prototype

Everything the prototype used to load from a CDN, held locally (CLAUDE.md —
*Self-Host Everything*). `App\Federations\Services\FederationApp` rewrites each
CDN URL to these files at serve time; the prototype files themselves are not
edited. Fetched 2026-09-24. React / ReactDOM / Babel were checked against the
prototype's own SRI hashes, and they match.

| File | Version | Source | Licence |
|---|---|---|---|
| `react.production.min.js` | 18.3.1 | unpkg.com/react | MIT |
| `react-dom.production.min.js` | 18.3.1 | unpkg.com/react-dom | MIT |
| `babel.min.js` | @babel/standalone 7.29.0 | unpkg.com | MIT (the runtime loads it only for JSX, and no page uses JSX) |
| `leaflet/` | 1.9.4 | unpkg.com/leaflet | BSD-2 (`leaflet/LICENSE`) |
| `mammoth.browser.min.js` | 1.6.0 | cdnjs | BSD-2 |
| `pdf.min.js`, `pdf.worker.min.js` | pdf.js 3.11.174 | cdnjs | Apache-2.0 |
| `xlsx.full.min.js` | SheetJS CE 0.20.2 | cdn.sheetjs.com | Apache-2.0 |
| `fonts/` | Poppins, Tajawal | Google Fonts | SIL OFL 1.1 (`fonts/OFL*.txt`) |

To update one: download the new file, update the URL pair in `FederationApp`,
and the version in this table. A rewrite that no longer matches the prototype
throws instead of silently loading a CDN.

Still remote, deliberately: the map TILES (openstreetmap.org), the same as every
other map on the platform.
